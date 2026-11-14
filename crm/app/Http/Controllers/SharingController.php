<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Services\WhatsApp\LeadMessenger;
use Illuminate\Http\Request;

/**
 * The company's website, contact details and social pages for the whole team, ready messages,
 * and one-click "company profile" / "Google review" messages to a client.
 */
class SharingController extends Controller
{
    public const KINDS = ['profile', 'review'];

    public function index(Request $request)
    {
        $c = CompanySetting::current();

        return view('share', [
            'c' => $c,
            'preview' => collect(self::KINDS)->mapWithKeys(fn ($k) => [$k => self::render($c, $k, __('Client'), $request->user())]),
            'bulkReview' => route('campaigns.create', ['name' => __('Google review request'), 'template_name' => 'review_request', 'status' => 'won',
                'var' => ['contact_name', 'review_link'], 'preview' => 1]),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $rules = ['website_email' => ['nullable', 'email', 'max:150'], 'website_phone' => ['nullable', 'string', 'max:30'],
            'msg_profile' => ['nullable', 'string', 'max:1500'], 'msg_review' => ['nullable', 'string', 'max:1500']];
        foreach (array_keys(CompanySetting::LINKS) as $k) {
            $rules['link_'.$k] = ['nullable', 'url:https,http', 'max:300'];
        }
        $data = $request->validate($rules, ['url' => __('Enter the full address, starting with https://')]);
        $c = CompanySetting::current();
        $c->update([
            'links' => collect(CompanySetting::LINKS)->keys()->mapWithKeys(fn ($k) => [$k => $data['link_'.$k] ?? null])->filter()->all(),
            'share_messages' => array_filter(['profile' => $data['msg_profile'] ?? null, 'review' => $data['msg_review'] ?? null]),
            'email' => $data['website_email'] ?? $c->email,
            'phone' => $data['website_phone'] ?? $c->phone,
        ]);
        CompanySetting::forget();
        AuditLog::record('share.settings');

        return back()->with('ok', __('Saved. The whole team now shares these links.'));
    }

    /** Sends the company profile or a Google review request on WhatsApp, from the client's own salesperson number. */
    public function send(Request $request, Lead $lead, string $kind, LeadMessenger $messenger)
    {
        $me = $request->user();
        abort_unless(in_array($kind, self::KINDS, true) && $lead->isVisibleTo($me), 404);
        $c = CompanySetting::current();
        if ($kind === 'review' && ! $c->link('google_review')) {
            return back()->withErrors(['share' => __('First the admin adds the Google review link under Share & reviews.')]);
        }
        $text = self::render($c, $kind, $lead->contact_name ?: $lead->firm_name, $me);
        if ($request->boolean('own')) {
            // The salesperson's own WhatsApp opens with the message ready; works at any time.
            $this->remember($lead, $kind, $me, true);
            $url = LeadMessenger::openLink($lead, $text);

            return $url ? redirect()->away($url) : back()->withErrors(['share' => __('This client has no phone number.')]);
        }
        if ($problem = $messenger->text($lead, $text, $me)) {
            return back()->withErrors(['share' => $problem]);
        }
        $this->remember($lead, $kind, $me, false);

        return back()->with('ok', $kind === 'review' ? __('Google review request sent on WhatsApp.') : __('Company profile sent on WhatsApp.'));
    }

    private function remember(Lead $lead, string $kind, User $me, bool $own): void
    {
        $what = $kind === 'review' ? 'Google review request' : 'Company profile and links';
        LeadActivity::create(['lead_id' => $lead->id, 'user_id' => $me->id, 'type' => 'whatsapp',
            'note' => $what.($own ? ' (opened in own WhatsApp)' : ' sent'), 'occurred_at' => now()]);
        if ($kind === 'review') {
            $lead->forceFill(['review_requested_at' => now()])->save();
        }
    }

    /** Fills the ready message; a line whose detail is missing (no website yet…) is left out. */
    public static function render(CompanySetting $c, string $kind, string $client, ?User $rep): string
    {
        $social = collect(CompanySetting::LINKS)->except(['website', 'google_review'])
            ->map(fn ($meta, $k) => $c->link($k) ? $meta[0].': '.$c->link($k) : null)->filter()->implode("\n");
        $vars = ['{name}' => $client, '{company}' => (string) $c->company_name, '{rep}' => (string) ($rep?->name ?? $c->company_name),
            '{links}' => $social, '{review}' => (string) $c->link('google_review'), '{website}' => (string) $c->link('website'),
            '{phone}' => (string) $c->phone, '{email}' => (string) $c->email];
        $lines = [];
        foreach (preg_split('/\r?\n/', $c->shareMessage($kind)) as $line) {
            preg_match_all('/\{[a-z]+\}/', $line, $m);
            $empty = collect($m[0])->contains(fn ($p) => isset($vars[$p]) && trim($vars[$p]) === '');
            if (! $empty) {
                $lines[] = strtr($line, $vars);
            }
        }

        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)) ?? '');
    }
}
