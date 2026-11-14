<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CompanySetting;
use App\Services\WhatsApp\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Sends a campaign one client at a time. On shared hosting the minute cron runs the queue. */
class SendCampaign implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public int $campaignId) {}

    public function handle(Client $client): void
    {
        $campaign = Campaign::with('number')->find($this->campaignId);
        if (! $campaign || $campaign->status === 'done') {
            return;
        }
        $co = CompanySetting::current();
        $company = $co?->company_name;
        $links = ['review_link' => $co?->link('google_review') ?: '-', 'website' => $co?->link('website') ?: '-'];
        $vars = $campaign->variables ?? [];
        $started = microtime(true);

        CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'pending')->with('lead.owner')->orderBy('id')
            ->chunkById(50, function ($rows) use ($campaign, $client, $vars, $company, $links, $started) {
                foreach ($rows as $r) {
                    $lead = $r->lead;
                    if (! $lead || $lead->whatsapp_opt_out || ! ($to = Client::toWaId($lead->phone))) {
                        $r->update(['status' => 'skipped', 'error' => $lead?->whatsapp_opt_out ? 'Opted out' : 'No phone']);
                        continue;
                    }
                    $params = collect($vars)->map(fn ($key) => match ($key) {
                        'contact_name' => $lead->contact_name ?: $lead->firm_name,
                        'firm_name' => $lead->firm_name,
                        'city' => $lead->city ?: '-',
                        'owner_name' => $lead->owner?->name ?: $company,
                        'company' => $company,
                        'review_link', 'website' => $links[$key],
                        default => '-',
                    })->values()->all();
                    $res = $client->sendTemplate($campaign->number, $to, $campaign->template_name, $campaign->template_language, $params);
                    $r->update(['status' => $res['ok'] ? 'sent' : 'failed', 'wamid' => $res['id'], 'error' => $res['error']]);
                    if ($res['ok'] && in_array('review_link', $vars, true)) {
                        $lead->forceFill(['review_requested_at' => now()])->saveQuietly();
                    }
                }
                // Stay well inside shared-hosting time limits; the next cron run continues.
                if (microtime(true) - $started > 240) {
                    return false;
                }
            });

        if (! CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'pending')->exists()) {
            $campaign->update(['status' => 'done']);
        } else {
            self::dispatch($campaign->id);
        }
    }
}
