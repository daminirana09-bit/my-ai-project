<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CompanySetting;
use App\Services\WhatsApp\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends a broadcast one client at a time, in the background. On shared hosting the minute cron
 * runs the queue; each run sends for up to 4 minutes and the next run carries on, so thousands
 * of clients go out without hitting hosting time limits. Waits for the start time, stops when
 * paused or cancelled, and keeps within WhatsApp's 24-hour limit.
 */
class SendCampaign implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public int $campaignId) {}

    public function handle(Client $client): void
    {
        $campaign = Campaign::with('number')->find($this->campaignId);
        if (! $campaign || ! $campaign->isRunning()) {
            return; // done, paused or cancelled: "Resume" starts it again
        }
        if ($campaign->scheduled_at?->isFuture()) {
            return; // the minute cron (campaigns:due) starts it on time
        }
        $budget = Campaign::remainingToday();
        if ($budget === 0) {
            $campaign->update(['status' => 'limit']); // campaigns:due carries on once the limit frees up

            return;
        }
        $campaign->update(['status' => 'sending']);
        $co = CompanySetting::current();
        $company = $co?->company_name;
        $links = ['review_link' => $co?->link('google_review') ?: '-', 'website' => $co?->link('website') ?: '-'];
        $vars = $campaign->variables ?? [];
        $header = $campaign->header_type ? ['type' => $campaign->header_type, 'link' => $campaign->header_url, 'filename' => mb_substr(preg_replace('/[^\w\- ]+/u', '', $campaign->name) ?: 'document', 0, 60).'.pdf'] : null;
        $started = microtime(true);
        $stoppedBy = null;

        CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'pending')->with('lead.owner')->orderBy('id')
            ->chunkById(50, function ($rows) use ($campaign, $client, $vars, $company, $links, $header, $started, &$budget, &$stoppedBy) {
                // Paused or cancelled from the screen while sending?
                if (! in_array(Campaign::whereKey($campaign->id)->value('status'), ['sending'], true)) {
                    $stoppedBy = 'user';

                    return false;
                }
                foreach ($rows as $r) {
                    if ($budget !== null && $budget <= 0) {
                        $stoppedBy = 'limit';

                        return false;
                    }
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
                    $res = $client->sendTemplate($campaign->number, $to, $campaign->template_name, $campaign->template_language, $params, $header);
                    $r->update(['status' => $res['ok'] ? 'sent' : 'failed', 'wamid' => $res['id'], 'error' => $res['error'], 'sent_at' => now()]);
                    if ($budget !== null) {
                        $budget--;
                    }
                    if ($res['ok'] && in_array('review_link', $vars, true)) {
                        $lead->forceFill(['review_requested_at' => now()])->saveQuietly();
                    }
                }
                // Stay well inside shared-hosting time limits; the next cron run continues.
                if (microtime(true) - $started > 240) {
                    return false;
                }
            });

        if ($stoppedBy === 'user') {
            return;
        }
        if (! CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'pending')->exists()) {
            $campaign->update(['status' => 'done', 'finished_at' => now()]);
        } elseif ($stoppedBy === 'limit') {
            $campaign->update(['status' => 'limit']);
        } else {
            self::dispatch($campaign->id);
        }
    }
}
