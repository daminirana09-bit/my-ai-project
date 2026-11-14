<?php

namespace App\Services\Leads;

use App\Models\LeadIntegration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches new enquiries with the company's API key (IndiaMART, TradeIndia, a portal's API link).
 * Runs every 10 minutes from cron and from the "Fetch now" button.
 */
class Puller
{
    /** IndiaMART blocks keys that call more often than once in 5 minutes. */
    public const MIN_GAP_MINUTES = 5;

    public const INDIAMART_URL = 'https://mapi.indiamart.com/wservce/crm/crmListing/v2/';
    public const TRADEINDIA_URL = 'https://www.tradeindia.com/utils/my_inquiry.html';

    public function __construct(private Normalizer $normalizer, private LeadIntake $intake, private EmailLeadParser $emails, private ImapMailbox $mailbox) {}

    /** @return array{ok: bool, message: string, counts: array<string,int>} */
    public function pull(LeadIntegration $i): array
    {
        if (! $i->enabled || ! $i->canPull()) {
            return ['ok' => false, 'message' => __('Not switched on, or the API key is missing.'), 'counts' => []];
        }
        if ($i->last_pulled_at && $i->last_pulled_at->gt(now()->subMinutes(self::MIN_GAP_MINUTES))) {
            return ['ok' => false, 'message' => __('Fetched less than 5 minutes ago. The portal allows one fetch every 5 minutes.'), 'counts' => []];
        }
        $i->forceFill(['last_pulled_at' => now()])->save();
        if ($i->platform === 'email') {
            return $this->pullEmail($i);
        }

        // Overlap the last window a little: anything seen twice is ignored.
        $from = $i->last_success_at ? $i->last_success_at->copy()->subMinutes(30) : now()->subDay();
        $from = $from->max(now()->subDays(6));
        $to = now();

        try {
            $records = match ($i->platform) {
                'indiamart' => $this->indiamart($i, $from, $to),
                'tradeindia' => $this->tradeindia($i, $from, $to),
                'exportersindia', 'other_portal' => $this->apiLink($i, $from, $to),
            };
        } catch (PullFailed $e) {
            $i->failed($e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage(), 'counts' => []];
        } catch (Throwable $e) {
            Log::warning('Lead fetch failed', ['platform' => $i->platform, 'error' => $e->getMessage()]);
            $i->failed('Could not reach '.$i->name().': '.mb_substr($e->getMessage(), 0, 200));

            return ['ok' => false, 'message' => 'Could not reach '.$i->name().'.', 'counts' => []];
        }

        $counts = ['created' => 0, 'existing' => 0, 'duplicate' => 0, 'skipped' => 0, 'test' => 0];
        foreach ($records as $row) {
            $d = $i->platform === 'indiamart' ? $this->normalizer->indiamart($row) : $this->normalizer->generic($row);
            $d->source = null; // a portal's own "source" field never replaces the portal's name
            $counts[$this->intake->receive($i->platform, $i, $d, $i->sourceLabel())]++;
        }
        $i->refresh();
        $i->forceFill(['last_success_at' => $to])->save();
        if ($counts['skipped'] === 0) {
            $i->forceFill(['last_error' => null, 'last_error_at' => null])->save();
        }

        return ['ok' => true, 'message' => 'Fetched', 'counts' => $counts];
    }

    /** New mail in the lead mailbox → leads. The mailbox itself is never changed. */
    private function pullEmail(LeadIntegration $i): array
    {
        try {
            $r = $this->mailbox->fetchNew((string) $i->conf('imap_host'), (int) $i->conf('imap_port', 993), (string) $i->conf('imap_user'),
                (string) $i->conf('imap_password'), (int) $i->conf('uidvalidity', 0), (int) $i->conf('last_uid', 0));
        } catch (PullFailed $e) {
            $i->failed($e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage(), 'counts' => []];
        } catch (Throwable $e) {
            Log::warning('Lead mailbox failed', ['error' => $e->getMessage()]);
            $i->failed('Could not read the mailbox: '.mb_substr($e->getMessage(), 0, 200));

            return ['ok' => false, 'message' => 'Could not read the mailbox.', 'counts' => []];
        }
        $counts = ['created' => 0, 'existing' => 0, 'duplicate' => 0, 'skipped' => 0, 'test' => 0];
        foreach ($r['messages'] as $raw) {
            try {
                [$d] = $this->emails->parse(MimeMessage::parse($raw), (string) $i->conf('imap_user'), (string) $i->conf('sender_rules', ''));
            } catch (Throwable $e) {
                Log::warning('Lead mail could not be read', ['error' => $e->getMessage()]);
                $d = null;
            }
            if ($d === null) {
                $counts['skipped']++;

                continue;
            }
            $counts[$this->intake->receive('email', $i, $d, 'Email')]++;
        }
        $config = $i->config ?? [];
        $config['uidvalidity'] = $r['uidvalidity'];
        $config['last_uid'] = $r['last_uid'];
        $i->forceFill(['config' => $config, 'last_success_at' => now(), 'last_error' => null, 'last_error_at' => null])->save();

        return ['ok' => true, 'message' => 'Fetched', 'counts' => $counts];
    }

    private function indiamart(LeadIntegration $i, Carbon $from, Carbon $to): array
    {
        $tz = 'Asia/Kolkata';
        $res = Http::timeout(30)->acceptJson()->get(self::INDIAMART_URL, [
            'glusr_crm_key' => $i->conf('crm_key'),
            'start_time' => $from->copy()->timezone($tz)->format('d-M-YH:i:s'),
            'end_time' => $to->copy()->timezone($tz)->format('d-M-YH:i:s'),
        ]);
        $json = $res->json();
        if (! is_array($json)) {
            throw new PullFailed('IndiaMART did not answer properly (HTTP '.$res->status().').');
        }
        $code = (int) ($json['CODE'] ?? $res->status());
        if ($code === 204 || (($json['TOTAL_RECORDS'] ?? null) === 0)) {
            return [];
        }
        if ($code !== 200) {
            throw new PullFailed('IndiaMART: '.mb_substr((string) ($json['MESSAGE'] ?? 'error '.$code), 0, 300));
        }

        return $this->normalizer->records($json['RESPONSE'] ?? []);
    }

    private function tradeindia(LeadIntegration $i, Carbon $from, Carbon $to): array
    {
        $all = [];
        for ($page = 1; $page <= 10; $page++) {
            $res = Http::timeout(30)->acceptJson()->get(self::TRADEINDIA_URL, [
                'userid' => $i->conf('user_id'),
                'profile_id' => $i->conf('profile_id'),
                'key' => $i->conf('key'),
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
                'limit' => 100,
                'page_no' => $page,
            ]);
            $json = $res->json();
            if (! $res->successful() || ! is_array($json)) {
                throw new PullFailed('TradeIndia did not answer properly (HTTP '.$res->status().'). Check the User ID, Profile ID and key.');
            }
            $rows = $this->normalizer->records($json);
            if ($rows === [] && ! array_is_list($json)) {
                $msg = $json['message'] ?? $json['error'] ?? $json['msg'] ?? null;
                if (is_string($msg) && ! preg_match('/no (inquir|enquir|record|data)/i', $msg)) {
                    throw new PullFailed('TradeIndia: '.mb_substr($msg, 0, 300));
                }
            }
            $all = array_merge($all, $rows);
            if (count($rows) < 100) {
                break;
            }
        }

        return $all;
    }

    private function apiLink(LeadIntegration $i, Carbon $from, Carbon $to): array
    {
        $url = strtr((string) $i->conf('api_url'), ['{from}' => $from->toDateString(), '{to}' => $to->toDateString()]);
        self::assertSafeUrl($url);
        $res = Http::timeout(30)->acceptJson()->withoutRedirecting()->get($url);
        $json = $res->json();
        if (! $res->successful() || ! is_array($json)) {
            throw new PullFailed($i->name().' did not answer with a list of enquiries (HTTP '.$res->status().'). Check the API link.');
        }

        return $this->normalizer->records($json);
    }

    /** Only public https addresses: the server must never be pointed at itself or the hosting's internal network. */
    public static function assertSafeUrl(string $url): void
    {
        $p = parse_url($url);
        if (($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user'])) {
            throw new PullFailed('The API link must start with https://');
        }
        $host = $p['host'];
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            throw new PullFailed('The API link\'s address could not be found.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new PullFailed('The API link points to a private address and is not allowed.');
            }
        }
    }
}
