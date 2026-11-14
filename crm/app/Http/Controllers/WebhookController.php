<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Services\ClashDetector;
use App\Services\WhatsApp\AutoAssigner;
use App\Services\WhatsApp\Inbound;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The only doors open to the internet (Rule 1). They can put data in, never take data out.
 */
class WebhookController extends Controller
{
    /** Meta checks the webhook URL once with a GET and our verify token. */
    public function verify(Request $request)
    {
        $token = (string) CompanySetting::current()?->wa('verify_token');
        if ($request->query('hub_mode') === 'subscribe' && $token !== '' && hash_equals($token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request, Inbound $inbound)
    {
        $secret = (string) CompanySetting::current()?->wa('app_secret');
        $sig = (string) $request->header('X-Hub-Signature-256');
        $raw = $request->getContent();
        if ($secret === '' || ! hash_equals('sha256='.hash_hmac('sha256', $raw, $secret), $sig)) {
            Log::warning('WhatsApp webhook rejected: bad signature', ['ip' => $request->ip()]);

            return response('Forbidden', 403);
        }
        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response('Bad request', 400);
        }
        try {
            $inbound->handle($payload);
        } catch (\Throwable $e) {
            // Answer 200 anyway so Meta does not keep retrying a message we cannot read; the error is logged.
            Log::error('WhatsApp webhook failed: '.$e->getMessage(), ['exception' => $e]);
        }

        return response('OK', 200);
    }

    /** Website enquiry form posts here with the company's secret token. */
    public function leadCapture(Request $request, string $token, ClashDetector $clashes, AutoAssigner $assigner)
    {
        $c = CompanySetting::current();
        if (! $c?->lead_capture_token || ! hash_equals($c->lead_capture_token, $token)) {
            return response()->json(['ok' => false], 404);
        }
        if (filled($request->input('website'))) {
            return $this->captureReply($request, true); // honeypot field filled = bot
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'firm' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:80'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);
        $existing = $clashes->findExisting($data['phone'], null);
        if ($existing) {
            LeadActivity::create(['lead_id' => $existing->id, 'type' => 'note', 'note' => 'Website enquiry again: '.mb_substr((string) ($data['message'] ?? ''), 0, 300), 'occurred_at' => now()]);
            $existing->forceFill(['last_contact_at' => now()])->save();
        } else {
            Lead::create([
                'firm_name' => $data['firm'] ?: $data['name'], 'contact_name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'] ?? null,
                'city' => $data['city'] ?? null, 'requirement' => isset($data['message']) ? mb_substr($data['message'], 0, 255) : null,
                'notes' => $data['message'] ?? null, 'source' => 'Website', 'status' => 'new', 'owner_id' => $assigner->pick()?->id, 'created_via' => 'website',
            ]);
        }

        return $this->captureReply($request, true);
    }

    private function captureReply(Request $request, bool $ok)
    {
        $back = (string) $request->input('redirect');
        if (! $request->expectsJson() && preg_match('#^https?://#i', $back)) {
            return redirect()->away($back);
        }

        return $request->expectsJson() ? response()->json(['ok' => $ok]) : response('Thank you. We will contact you soon.', 200)->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
