<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\LeadIntegration;
use App\Services\Leads\LeadIntake;
use App\Services\Leads\Normalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receiving links for lead platforms: /webhook/leads/{platform}/{secret}.
 * Write-only (Rule 1): they put leads in and never give any data back.
 */
class LeadWebhookController extends Controller
{
    private const MAX_RECORDS = 100;

    public function __construct(private Normalizer $normalizer, private LeadIntake $intake) {}

    public function handle(Request $request, string $platform, string $token)
    {
        $i = LeadIntegration::where('platform', $platform)->first();
        if (! $i || ! hash_equals($i->token, $token)) {
            return response()->json(['ok' => false], 404);
        }
        // Facebook checks the link once while it is being set up, before it is switched on.
        if ($platform === 'facebook' && $request->isMethod('get')) {
            return $this->facebookVerify($request, $i);
        }
        if (! $i->enabled) {
            return response()->json(['ok' => false], 404);
        }
        if ($request->isMethod('get') && ! in_array($platform, ['justdial', 'custom'], true)) {
            return response()->json(['ok' => false], 405);
        }

        return match ($platform) {
            'facebook' => $this->facebook($request, $i),
            'google_ads' => $this->google($request, $i),
            default => $this->generic($request, $i),
        };
    }

    private function generic(Request $request, LeadIntegration $i)
    {
        $payload = $request->isJson() ? $request->json()->all() : $request->all();
        if ($payload === [] && ($raw = $request->getContent()) !== '') {
            $payload = (array) (json_decode($raw, true) ?: []);
            if ($payload === []) {
                parse_str($raw, $payload);
            }
        }
        $records = $this->normalizer->records($payload);
        if ($records === [] && $payload !== [] && ! array_is_list($payload)) {
            $records = [$payload]; // one enquiry sent as plain fields
        }
        $counts = [];
        foreach (array_slice($records, 0, self::MAX_RECORDS) as $row) {
            $d = $i->platform === 'indiamart' ? $this->normalizer->indiamart($row) : $this->normalizer->generic($row);
            if ($i->platform !== 'custom') {
                $d->source = null;
            }
            $r = $this->intake->receive($i->platform, $i, $d, $i->sourceLabel());
            $counts[$r] = ($counts[$r] ?? 0) + 1;
        }

        return match ($i->platform) {
            'justdial' => response('RECEIVED', 200)->header('Content-Type', 'text/plain'),
            'indiamart' => response()->json(['CODE' => 200, 'STATUS' => 'SUCCESS']),
            default => response()->json(['ok' => true, 'received' => array_sum($counts), 'new' => $counts['created'] ?? 0]),
        };
    }

    private function google(Request $request, LeadIntegration $i)
    {
        $body = $request->json()->all();
        $key = (string) $i->conf('google_key');
        if ($key === '' || ! hash_equals($key, (string) ($body['google_key'] ?? ''))) {
            return response()->json(['ok' => false], 403);
        }
        $this->intake->receive('google_ads', $i, $this->normalizer->google($body), $i->sourceLabel());

        return response()->json((object) []);
    }

    private function facebookVerify(Request $request, LeadIntegration $i)
    {
        $token = (string) $i->conf('verify_token');
        if ($request->query('hub_mode') === 'subscribe' && $token !== '' && hash_equals($token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    private function facebook(Request $request, LeadIntegration $i)
    {
        $secret = (string) $i->conf('app_secret');
        $raw = $request->getContent();
        if ($secret === '' || ! hash_equals('sha256='.hash_hmac('sha256', $raw, $secret), (string) $request->header('X-Hub-Signature-256'))) {
            Log::warning('Facebook lead webhook rejected: bad signature', ['ip' => $request->ip()]);

            return response('Forbidden', 403);
        }
        $payload = json_decode($raw, true) ?: [];
        $pageId = (string) $i->conf('page_id');
        $failed = false;
        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $v = $change['value'] ?? [];
                if (($change['field'] ?? '') !== 'leadgen' || empty($v['leadgen_id'])) {
                    continue;
                }
                if ($pageId !== '' && isset($v['page_id']) && (string) $v['page_id'] !== $pageId) {
                    continue;
                }
                $lead = $this->fetchFacebookLead($i, (string) $v['leadgen_id']);
                if ($lead === null) {
                    $failed = true;

                    continue;
                }
                $this->intake->receive('facebook', $i, $this->normalizer->facebook($lead), $i->sourceLabel());
            }
        }

        // Facebook sends it again later if we could not read the lead now; repeats are ignored.
        return $failed ? response('Try again', 503) : response('OK', 200);
    }

    private function fetchFacebookLead(LeadIntegration $i, string $leadgenId): ?array
    {
        if (! preg_match('/^\d{5,25}$/', $leadgenId)) {
            return null;
        }
        $version = CompanySetting::current()?->wa('api_version') ?: 'v23.0';
        try {
            $res = Http::withToken((string) $i->conf('page_token'))->acceptJson()->timeout(20)
                ->get("https://graph.facebook.com/{$version}/{$leadgenId}", ['fields' => 'id,created_time,field_data,ad_name,adset_name,campaign_name,form_id,platform,is_organic']);
            if ($res->successful() && is_array($res->json('field_data'))) {
                return $res->json();
            }
            $i->failed('Facebook: '.mb_substr((string) ($res->json('error.message') ?? 'HTTP '.$res->status()), 0, 300).' Check the Page access token.');
        } catch (Throwable $e) {
            $i->failed('Could not reach Facebook: '.mb_substr($e->getMessage(), 0, 200));
        }

        return null;
    }
}
