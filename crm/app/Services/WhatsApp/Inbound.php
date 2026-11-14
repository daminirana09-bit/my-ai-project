<?php

namespace App\Services\WhatsApp;

use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\CampaignRecipient;
use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Models\WhatsappNumber;
use App\Notifications\WhatsAppUrgent;
use App\Services\ClashDetector;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/** Handles what Meta sends to the webhook: new client messages and delivery updates. */
class Inbound
{
    public function __construct(
        private LanguageDetector $lang,
        private IntentDetector $intent,
        private ReplySuggester $replies,
        private Client $client,
        private ClashDetector $clashes,
        private AutoAssigner $assigner,
    ) {}

    public function handle(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $v = $change['value'] ?? [];
                $number = WhatsappNumber::where('phone_number_id', (string) ($v['metadata']['phone_number_id'] ?? ''))->where('active', true)->first();
                if (! $number) {
                    continue; // not one of this company's numbers
                }
                $names = collect($v['contacts'] ?? [])->mapWithKeys(fn ($c) => [(string) ($c['wa_id'] ?? '') => $c['profile']['name'] ?? null]);
                foreach ($v['messages'] ?? [] as $m) {
                    $this->message($number, $m, $names[(string) ($m['from'] ?? '')] ?? null);
                }
                foreach ($v['statuses'] ?? [] as $s) {
                    $this->status($s);
                }
            }
        }
    }

    private function message(WhatsappNumber $number, array $m, ?string $profileName): void
    {
        $wamid = (string) ($m['id'] ?? '');
        $from = preg_replace('/\D+/', '', (string) ($m['from'] ?? ''));
        if ($from === '' || ($wamid !== '' && WaMessage::where('wamid', $wamid)->exists())) {
            return; // Meta retries deliveries; each message is stored once
        }
        $body = $this->text($m);
        $at = isset($m['timestamp']) ? CarbonImmutable::createFromTimestamp((int) $m['timestamp'])->setTimezone(config('app.timezone')) : now();

        [$c, $msg] = DB::transaction(function () use ($number, $from, $profileName, $body, $wamid, $at) {
            $hash = Lead::hashPhone($from);
            $c = WaConversation::firstOrNew(['whatsapp_number_id' => $number->id, 'wa_id_hash' => $hash]);
            if (! $c->exists) {
                $c->wa_id = $from;
            }
            if ($profileName) {
                $c->contact_name = mb_substr($profileName, 0, 255);
            }
            $language = $this->lang->detect($body);
            $intent = $this->intent->detect($body);
            // Keep the language once known; a short "ok" in English should not switch a Tamil client to English.
            if (! $c->language || mb_strlen((string) $body) > 12) {
                $c->language = $language;
            }
            $c->last_intent = $intent;
            $c->last_inbound_at = $at;
            $c->last_message_at = $at;
            $c->unread = (int) $c->unread + 1;
            $c->save();

            $this->linkLead($c, $number, $from);

            $msg = WaMessage::create(['wa_conversation_id' => $c->id, 'direction' => 'in', 'body' => $body, 'type' => 'text', 'wamid' => $wamid ?: null,
                'status' => 'received', 'language' => $language, 'intent' => $intent, 'sent_at' => $at]);

            if ($c->lead) {
                $c->lead->forceFill(['last_contact_at' => now(), 'language' => $c->lead->language ?: $c->language])->save();
                if (in_array(strtoupper(trim((string) $body)), ['STOP', 'UNSUBSCRIBE', 'STOP ALL'], true)) {
                    $c->lead->forceFill(['whatsapp_opt_out' => true])->save();
                }
                LeadActivity::create(['lead_id' => $c->lead_id, 'user_id' => null, 'type' => 'whatsapp', 'note' => mb_substr((string) $body, 0, 300), 'occurred_at' => $at]);
            }

            return [$c->fresh(['number.user', 'lead']), $msg];
        });

        $this->cover($c, $msg);
    }

    private function text(array $m): string
    {
        $type = $m['type'] ?? 'text';

        return match ($type) {
            'text' => (string) ($m['text']['body'] ?? ''),
            'button' => (string) ($m['button']['text'] ?? ''),
            'interactive' => (string) ($m['interactive']['button_reply']['title'] ?? $m['interactive']['list_reply']['title'] ?? '[interactive]'),
            'image', 'video', 'document', 'audio', 'sticker' => '['.$type.']'.(isset($m[$type]['caption']) ? ' '.$m[$type]['caption'] : ''),
            'location' => '[location]',
            default => '['.$type.']',
        };
    }

    /** Attach the chat to the client's lead; create one if new; flag a clash if another rep owns it. */
    private function linkLead(WaConversation $c, WhatsappNumber $number, string $from): void
    {
        if ($c->lead_id) {
            return;
        }
        $lead = $this->clashes->findExisting($from, null);
        if (! $lead) {
            $owner = $number->user ?? $this->assigner->pick();
            $lead = Lead::create([
                'firm_name' => $c->contact_name ?: '+'.$from,
                'contact_name' => $c->contact_name,
                'phone' => $from,
                'source' => 'WhatsApp',
                'status' => 'new',
                'owner_id' => $owner?->id,
                'created_via' => 'whatsapp',
                'language' => $c->language,
            ]);
        } elseif ($number->user && $lead->owner_id && (int) $lead->owner_id !== $number->user->id) {
            $this->clashes->report($lead, $number->user, 'contacted', 'WhatsApp '.$number->display_phone);
        } elseif (! $lead->owner_id) {
            $owner = $number->user ?? $this->assigner->pick();
            $lead->update(['owner_id' => $owner?->id]);
        }
        $c->lead_id = $lead->id;
        $c->save();
    }

    /** AI cover: reply when the salesperson is away, queue a call-back, and raise urgent messages. */
    private function cover(WaConversation $c, WaMessage $msg): void
    {
        $co = CompanySetting::current();
        $rep = $c->number?->user;
        [$away, $back] = $this->awayState($rep, $co);

        if ($away) {
            $c->waiting_callback = true;
        }
        if ($this->intent->isUrgent($msg->body)) {
            $c->urgent = true;
            if ($co->automation('urgent_alert')) {
                $ids = User::active()->where('role', 'admin')->pluck('id')->merge(array_filter([$rep?->manager_id, $away ? $rep?->backup_user_id : null]))->unique();
                $people = User::active()->whereIn('id', $ids)->get();
                if ($rep && ! $away) {
                    $people->push($rep);
                }
                Notification::send($people->unique('id'), new WhatsAppUrgent($c));
            }
        }
        $c->save();

        $alreadyReplied = $c->auto_replied_at && $c->auto_replied_at->gt(now()->subHours(12));
        if (! $away || $alreadyReplied || ! $this->client->configured()) {
            return;
        }
        $tpl = $this->replies->template('away', $c->language ?: 'en');
        if (! $tpl) {
            return;
        }
        $text = \App\Models\ReplyTemplate::render($tpl->body, $this->replies->vars($c, $rep, $back));
        $res = $this->client->sendText($c->number, (string) $c->wa_id, $text);
        WaMessage::create(['wa_conversation_id' => $c->id, 'direction' => 'out', 'body' => $text, 'type' => 'auto', 'wamid' => $res['id'],
            'status' => $res['ok'] ? 'sent' : 'failed', 'error' => $res['error'], 'language' => $tpl->language, 'sent_at' => now()]);
        $c->forceFill(['auto_replied_at' => now(), 'last_message_at' => now()])->save();
        if (! $res['ok']) {
            Log::warning('WhatsApp auto-reply failed', ['conversation' => $c->id, 'error' => $res['error']]);
        }
    }

    /** @return array{0:bool,1:?string} away?, and when they are back (as text) */
    public function awayState(?User $rep, CompanySetting $co): array
    {
        if ($rep && $co->automation('away_auto_reply')) {
            if ($rep->isAway()) {
                $u = $rep->away_until;

                return [true, $u->isToday() ? $u->format('g:i A') : $u->format('d M, g:i A')];
            }
            $onLeave = AttendanceDay::where('user_id', $rep->id)->whereDate('date', today())->where('status', 'leave')->exists();
            if ($onLeave) {
                return [true, today()->addDay()->format('d M')];
            }
        }
        if ($rep && $co->automation('absent_auto_reply') && ($back = $this->absentBack($rep, $co))) {
            return [true, $back];
        }
        if ($co->automation('after_hours_auto_reply')) {
            $shift = $rep ? $rep->shift($co) : ['office_start' => $co->office_start, 'office_end' => $co->office_end];
            $now = now();
            $start = $now->copy()->setTimeFromTimeString($shift['office_start']);
            $end = $now->copy()->setTimeFromTimeString($shift['office_end']);
            $off = in_array($now->dayOfWeek, $co->weeklyOffDays(), true);
            if ($off || $now->lt($start) || $now->gt($end)) {
                return [true, $this->nextStart($shift, $co, $now->lt($start) && ! $off)->format('d M, g:i A')];
            }
        }

        return [false, null];
    }

    /**
     * During office hours, a salesperson who uses attendance but has not punched in an hour after
     * their start time, or has punched out and left (not just for lunch), is treated as away.
     * Returns when they are expected back, or null when they are at work.
     */
    private function absentBack(User $rep, CompanySetting $co): ?string
    {
        $now = now();
        $shift = $rep->shift($co);
        $start = $now->copy()->setTimeFromTimeString($shift['office_start']);
        $end = $now->copy()->setTimeFromTimeString($shift['office_end']);
        if (in_array($now->dayOfWeek, $co->weeklyOffDays(), true) || $now->lt($start) || $now->gt($end)) {
            return null; // outside office hours: the after-hours reply decides
        }
        // Only for people who actually punch in; a company that does not use attendance is never "absent".
        if (! AttendancePunch::where('user_id', $rep->id)->where('punched_at', '>=', $now->copy()->subDays(14))->exists()) {
            return null;
        }
        $last = AttendancePunch::where('user_id', $rep->id)->where('punched_at', '>=', $now->copy()->startOfDay())->orderByDesc('punched_at')->orderByDesc('id')->first();
        if (! $last) {
            return $now->gte($start->copy()->addMinutes(60)) ? $this->nextStart($shift, $co, false)->format('d M, g:i A') : null;
        }
        if ($last->type === 'out') {
            $lunchStart = $now->copy()->setTimeFromTimeString($shift['lunch_start'] ?: '00:00');
            $lunchEnd = $now->copy()->setTimeFromTimeString($shift['lunch_end'] ?: '00:00');
            if ($shift['lunch_start'] && $now->between($lunchStart, $lunchEnd)) {
                return null; // out for lunch only
            }

            return $this->nextStart($shift, $co, false)->format('d M, g:i A');
        }

        return null;
    }

    /** Start of the next working day (or of today, if $today and it has not started yet). */
    private function nextStart(array $shift, CompanySetting $co, bool $today): \Carbon\Carbon
    {
        $next = now()->setTimeFromTimeString($shift['office_start']);
        if (! $today) {
            $next = $next->addDay();
        }
        for ($i = 0; $i < 7 && in_array($next->dayOfWeek, $co->weeklyOffDays(), true); $i++) {
            $next = $next->addDay();
        }

        return $next;
    }

    private function status(array $s): void
    {
        $id = (string) ($s['id'] ?? '');
        $status = (string) ($s['status'] ?? '');
        if ($id === '' || ! in_array($status, ['sent', 'delivered', 'read', 'failed'], true)) {
            return;
        }
        $error = isset($s['errors'][0]) ? mb_substr(($s['errors'][0]['title'] ?? 'Error').' ('.($s['errors'][0]['code'] ?? '?').')', 0, 250) : null;
        $rank = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4, 'pending' => 0, 'received' => 0, 'skipped' => 0];
        foreach ([WaMessage::where('wamid', $id)->first(), CampaignRecipient::where('wamid', $id)->first()] as $row) {
            // Updates can arrive out of order: never go back from "read" to "delivered".
            if ($row && ($rank[$status] ?? 0) >= ($rank[$row->status] ?? 0)) {
                $row->update(['status' => $status, 'error' => $error ?? $row->error]);
            }
        }
    }
}
