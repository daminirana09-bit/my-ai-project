<?php

namespace App\Services\Leads;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadInbound;
use App\Models\LeadIntegration;
use App\Models\User;
use App\Notifications\NewLeadArrived;
use App\Services\ClashDetector;
use App\Services\WhatsApp\AutoAssigner;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The one way leads from outside come in: IndiaMART, TradeIndia, JustDial, Facebook, Google Ads,
 * the website form, other apps. Every lead is tagged with where it came from, the same enquiry
 * never becomes two leads, and a client already in the CRM gets a note instead of a new lead.
 */
class LeadIntake
{
    public function __construct(private ClashDetector $clashes, private AutoAssigner $assigner) {}

    /** @return string created | existing | duplicate | test | skipped */
    public function receive(string $platform, ?LeadIntegration $from, LeadData $d, string $defaultSource): string
    {
        $source = $d->source ?: $defaultSource;
        $from?->forceFill(['last_fields' => mb_substr(implode(', ', array_slice($d->fieldNames, 0, 60)), 0, 1000) ?: null]);

        if ($d->test) {
            $this->remember($platform, $d->dedupeId(), null, 'test');
            $from?->forceFill(['last_success_at' => now(), 'last_error' => null, 'last_error_at' => null])->save();

            return 'test';
        }
        if (! $d->hasContact()) {
            $from?->failed('An enquiry without a phone number or email was skipped. Fields received: '.implode(', ', array_slice($d->fieldNames, 0, 30)));

            return 'skipped';
        }

        $tags = collect([$source])->merge($d->tags)->merge($from?->extraTags() ?? [])
            ->filter()->unique(fn ($t) => mb_strtolower($t))->take(12)->values()->all();

        $result = DB::transaction(function () use ($platform, $from, $d, $source, $tags) {
            try {
                $inbound = LeadInbound::create(['platform' => $platform, 'external_id' => $d->dedupeId(), 'result' => 'created']);
            } catch (UniqueConstraintViolationException) {
                return 'duplicate';
            }

            $existing = $this->clashes->findExisting($d->phone, null)
                ?? ($d->email ? Lead::where('email_hash', Lead::hashEmail($d->email))->orderBy('id')->first() : null);

            if ($existing) {
                $what = trim(implode(' — ', array_filter([$d->product, $d->message ? mb_substr($d->message, 0, 400) : null])));
                LeadActivity::create(['lead_id' => $existing->id, 'user_id' => null, 'type' => 'enquiry',
                    'note' => mb_substr($source.($what !== '' ? ': '.$what : ''), 0, 1000), 'occurred_at' => now()]);
                $existing->tags = collect($existing->tags ?? [])->merge($tags)->unique(fn ($t) => mb_strtolower($t))->take(20)->values()->all();
                $existing->campaign ??= $d->campaign;
                $existing->save();
                $existing->touch();
                $inbound->forceFill(['lead_id' => $existing->id, 'result' => 'existing'])->save();
                $this->tell($existing, $source, true);

                return 'existing';
            }

            $owner = $this->assigner->pick($from?->assign_to ?: null);
            $lead = Lead::create([
                'firm_name' => $d->firm ?? $d->name ?? $d->phone ?? $d->email,
                'contact_name' => $d->name,
                'phone' => $d->phone,
                'email' => $d->email,
                'city' => $d->city,
                'state' => $d->state,
                'requirement' => $d->product ?? ($d->message ? mb_substr($d->message, 0, 255) : null),
                'notes' => $d->message,
                'source' => mb_substr($source, 0, 40),
                'tags' => $tags,
                'campaign' => $d->campaign,
                'external_ref' => $d->externalId,
                'status' => 'new',
                'owner_id' => $owner?->id,
                'created_via' => $platform === 'website' ? 'website' : 'integration',
            ]);
            $inbound->forceFill(['lead_id' => $lead->id])->save();
            $this->tell($lead, $source, false);

            return 'created';
        });

        if ($from && in_array($result, ['created', 'existing'], true)) {
            $from->forceFill(['received' => $from->received + 1, 'last_lead_at' => now(), 'last_success_at' => now()])->save();
        } else {
            $from?->save();
        }

        return $result;
    }

    private function remember(string $platform, string $id, ?int $leadId, string $result): void
    {
        try {
            LeadInbound::create(['platform' => $platform, 'external_id' => $id, 'lead_id' => $leadId, 'result' => $result]);
        } catch (UniqueConstraintViolationException) {
            // already seen
        }
    }

    /** The owner hears about it; a lead nobody owns goes to the admins. */
    private function tell(Lead $lead, string $source, bool $again): void
    {
        $to = $lead->owner_id ? User::active()->whereKey($lead->owner_id)->get() : collect();
        if ($to->isEmpty()) {
            $to = User::active()->where('role', 'admin')->get();
        }
        Notification::send($to, new NewLeadArrived($lead, $source, $again));
    }
}
