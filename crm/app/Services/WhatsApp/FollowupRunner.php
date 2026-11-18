<?php

namespace App\Services\WhatsApp;

use App\Models\Campaign;
use App\Models\CompanySetting;
use App\Models\FollowupLog;
use App\Models\FollowupRule;
use App\Models\Holiday;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\ReplyTemplate;
use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Models\WhatsappNumber;
use App\Services\Translation\TranslationFailed;
use App\Services\Translation\Translator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Automatic WhatsApp follow-ups, checked every 15 minutes during office hours on working days.
 * Each client gets a rule's message at most once in its "repeat" days, never after STOP, and all
 * of it stays within the WhatsApp daily limit shared with campaigns. Within 24 hours of the
 * client's last message the rule's own text is sent (in the client's language); otherwise the
 * approved template.
 */
class FollowupRunner
{
    private const PER_RUN = 150;

    public function __construct(private Client $client) {}

    public static function officeOpen(): bool
    {
        $co = CompanySetting::current();
        $now = now();
        if (in_array($now->dayOfWeek, $co->weeklyOffDays(), true) || Holiday::isHoliday($now)) {
            return false;
        }

        return $now->between($now->copy()->setTimeFromTimeString($co->office_start), $now->copy()->setTimeFromTimeString($co->office_end));
    }

    /** @return array{sent: int, failed: int, skipped: int} */
    public function run(): array
    {
        $done = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        if (! $this->client->configured() || ! self::officeOpen()) {
            return $done;
        }
        foreach (FollowupRule::where('active', true)->orderBy('id')->get() as $rule) {
            foreach ($this->due($rule)->orderBy('leads.id')->limit(self::PER_RUN)->get() as $lead) {
                $budget = Campaign::remainingToday();
                if ($budget === 0) {
                    return $done; // WhatsApp's 24-hour limit: carry on later
                }
                $done[$this->send($rule, $lead)]++;
            }
            $rule->forceFill(['last_run_at' => now()])->save();
        }

        return $done;
    }

    /** Clients this rule would message now. */
    public function due(FollowupRule $rule): Builder
    {
        $after = (int) $rule->after;
        $open = ['new', 'contacted', 'interested', 'quote_sent', 'negotiation'];
        $q = Lead::query()->whereNotNull('phone_hash')->where('whatsapp_opt_out', false)
            ->whereDoesntHave('followupLogs', fn ($l) => $l->where('followup_rule_id', $rule->id)->where('sent_at', '>=', now()->subDays(max(1, (int) $rule->repeat_days))));

        return match ($rule->trigger) {
            'follow_up_due' => $q->whereIn('status', $open)->whereDate('next_follow_up_on', '<=', today()->subDays($after))
                ->whereDate('next_follow_up_on', '>=', today()->subDays($after + 30))
                ->where(fn ($w) => $w->whereNull('last_contact_at')->orWhereColumn('last_contact_at', '<', 'next_follow_up_on')),
            'quote_no_reply' => $q->whereIn('status', $open)->whereHas('quotes', fn ($x) => $x->where('status', 'sent')
                ->where('sent_at', '<=', now()->subDays($after))->where('sent_at', '>=', now()->subDays($after + 30)))
                ->whereDoesntHave('conversations.messages', fn ($m) => $m->where('direction', 'in')->where('sent_at', '>=', now()->subDays($after))),
            'no_reply' => $q->whereIn('status', ['contacted', 'interested', 'quote_sent', 'negotiation'])
                ->where(fn ($w) => $w->where('last_contact_at', '<=', now()->subDays($after))->orWhere(fn ($x) => $x->whereNull('last_contact_at')->where('created_at', '<=', now()->subDays($after))))
                ->whereDoesntHave('conversations.messages', fn ($m) => $m->where('direction', 'in')->where('sent_at', '>=', now()->subDays($after))),
            'reorder' => $q->where('status', 'won')->whereHas('sales', fn ($s) => $s->where('status', 'confirmed'))
                ->whereDoesntHave('sales', fn ($s) => $s->where('status', 'confirmed')->where('sale_date', '>', today()->subDays($after)->toDateString())),
            'new_lead' => $q->where('status', 'new')->whereNull('last_contact_at')->where('created_at', '<=', now()->subHours($after))->where('created_at', '>=', now()->subDays(7)),
            default => $q->whereRaw('1 = 0'),
        };
    }

    /** @return string sent | failed | skipped */
    private function send(FollowupRule $rule, Lead $lead): string
    {
        $log = function (string $status, ?string $error = null, ?string $wamid = null) use ($rule, $lead): string {
            FollowupLog::create(['followup_rule_id' => $rule->id, 'lead_id' => $lead->id, 'status' => $status,
                'error' => $error ? mb_substr($error, 0, 250) : null, 'wamid' => $wamid, 'sent_at' => now()]);

            return $status;
        };
        $to = Client::toWaId($lead->phone);
        $number = WhatsappNumber::where('active', true)->where('user_id', $lead->owner_id)->first() ?? WhatsappNumber::where('active', true)->whereNull('user_id')->first();
        if (! $to || ! $number) {
            return $log('skipped', ! $to ? 'No phone' : 'No WhatsApp number for this salesperson or the company');
        }
        $conv = WaConversation::firstOrCreate(['whatsapp_number_id' => $number->id, 'wa_id_hash' => Lead::hashPhone($to)],
            ['wa_id' => $to, 'lead_id' => $lead->id, 'language' => $lead->language, 'assigned_to' => $number->user_id ?? $lead->owner_id]);
        $vars = ['name' => $lead->contact_name ?: $lead->firm_name, 'firm' => $lead->firm_name, 'company' => (string) CompanySetting::current()->company_name,
            'rep' => $lead->owner?->name ?: (string) CompanySetting::current()->company_name, 'city' => $lead->city ?: '-'];

        if ($conv->canReplyFreely() && filled($rule->text)) {
            $body = $this->inClientLanguage(ReplyTemplate::render((string) $rule->text, $vars), $conv->language ?: $lead->language);
            $res = $this->client->sendText($number, $to, $body);
            $type = 'auto';
        } elseif (filled($rule->template_name)) {
            $params = collect($rule->variables ?? [])->map(fn ($k) => match ($k) {
                'contact_name' => $vars['name'], 'firm_name' => $vars['firm'], 'city' => $vars['city'], 'owner_name' => $vars['rep'], 'company' => $vars['company'],
                'review_link' => CompanySetting::current()->link('google_review') ?: '-', 'website' => CompanySetting::current()->link('website') ?: '-', default => '-',
            })->values()->all();
            $res = $this->client->sendTemplate($number, $to, $rule->template_name, $rule->template_language ?: 'en', $params);
            $body = __('Template: :t', ['t' => $rule->template_name]);
            $type = 'template';
        } else {
            return $log('skipped', 'Client wrote more than 24 hours ago and the rule has no approved template');
        }
        WaMessage::create(['wa_conversation_id' => $conv->id, 'direction' => 'out', 'body' => $body, 'type' => $type, 'wamid' => $res['id'],
            'status' => $res['ok'] ? 'sent' : 'failed', 'error' => $res['error'], 'language' => $conv->language, 'intent' => 'followup', 'sent_at' => now()]);
        $conv->forceFill(['last_message_at' => now()])->save();
        LeadActivity::create(['lead_id' => $lead->id, 'user_id' => null, 'type' => 'whatsapp', 'note' => mb_substr(__('Automatic follow-up: :r', ['r' => $rule->name]), 0, 300), 'occurred_at' => now()]);

        return $log($res['ok'] ? 'sent' : 'failed', $res['error'], $res['id']);
    }

    private function inClientLanguage(string $text, ?string $lang): string
    {
        $lang = $lang ?: 'en';
        if (in_array($lang, ['en', 'hing'], true)) {
            return $text;
        }
        $tr = app(Translator::class);
        if (! $tr->enabled()) {
            return $text;
        }
        try {
            $r = $tr->translate($text, app(LanguageDetector::class)->detect($text), $lang);

            return $r['warnings'] ? $text : $r['text'];
        } catch (TranslationFailed) {
            return $text;
        }
    }

    /** For the rules page: replies within 7 days of a follow-up. */
    public static function replies(FollowupRule $rule, int $days = 30): int
    {
        return FollowupLog::where('followup_rule_id', $rule->id)->where('status', 'sent')->where('sent_at', '>=', now()->subDays($days))->get(['lead_id', 'sent_at'])
            ->filter(fn ($l) => WaMessage::where('direction', 'in')->whereBetween('sent_at', [$l->sent_at, $l->sent_at->copy()->addDays(7)])
                ->whereHas('conversation', fn ($c) => $c->where('lead_id', $l->lead_id))->exists())->count();
    }
}
