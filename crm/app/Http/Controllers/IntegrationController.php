<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\LeadInbound;
use App\Models\LeadIntegration;
use App\Models\User;
use App\Services\Leads\Puller;
use App\Services\Leads\PullFailed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/** Admin: connect IndiaMART, TradeIndia, JustDial, Facebook, Google Ads and other lead sources. */
class IntegrationController extends Controller
{
    public function index()
    {
        $list = collect(array_keys(LeadIntegration::PLATFORMS))->map(fn ($p) => LeadIntegration::for($p));
        $month = now()->startOfMonth();
        $counts = LeadInbound::whereIn('result', ['created', 'existing'])->where('created_at', '>=', $month)
            ->selectRaw('platform, COUNT(*) as n')->groupBy('platform')->pluck('n', 'platform');
        $today = LeadInbound::whereIn('result', ['created', 'existing'])->where('created_at', '>=', today())
            ->selectRaw('platform, COUNT(*) as n')->groupBy('platform')->pluck('n', 'platform');
        $bySource = Lead::where('created_at', '>=', $month)
            ->selectRaw("COALESCE(source, '—') as src, COUNT(*) as total, SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won")
            ->groupBy('src')->orderByDesc('total')->get();

        return view('integrations', [
            'list' => $list,
            'counts' => $counts,
            'today' => $today,
            'bySource' => $bySource,
            'people' => User::active()->whereIn('role', ['rep', 'manager'])->orderBy('name')->get(['id', 'name']),
            'c' => CompanySetting::current(),
        ]);
    }

    public function update(Request $request, string $platform)
    {
        $i = LeadIntegration::for($platform);
        $rules = ['enabled' => ['nullable', 'boolean'], 'assign_to' => ['nullable', 'array'], 'assign_to.*' => ['integer'], 'extra_tags' => ['nullable', 'string', 'max:255']];
        foreach ($i->info('fields', []) as $key => [$label, $secret]) {
            $rules[$key] = ['nullable', 'string', $key === 'api_url' ? 'max:1000' : 'max:500'];
        }
        $data = $request->validate($rules);

        $config = $i->config ?? [];
        foreach ($i->info('fields', []) as $key => [$label, $secret]) {
            $v = trim((string) ($data[$key] ?? ''));
            if ($secret && $v === '') {
                continue; // empty = keep the saved key
            }
            $config[$key] = $v === '' ? null : $v;
        }
        if (filled($config['api_url'] ?? null)) {
            try {
                Puller::assertSafeUrl(strtr($config['api_url'], ['{from}' => '2026-01-01', '{to}' => '2026-01-02']));
            } catch (PullFailed $e) {
                return back()->withErrors(['api_url' => $e->getMessage()])->withInput();
            }
        }
        if ($platform === 'other_portal') {
            $config['source_name'] = mb_substr(trim((string) ($config['source_name'] ?? '')), 0, 40) ?: null;
        }

        $pool = User::active()->whereIn('role', ['rep', 'manager'])->whereIn('id', $data['assign_to'] ?? [])->pluck('id')->all();
        $i->fill([
            'enabled' => $request->boolean('enabled'),
            'config' => $config,
            'assign_to' => $pool ?: null,
            'extra_tags' => collect(explode(',', (string) ($data['extra_tags'] ?? '')))->map(fn ($t) => trim(strip_tags($t)))->filter()->unique()->take(5)->implode(', ') ?: null,
        ])->save();
        AuditLog::record('integration.update', $platform, ['enabled' => $i->enabled]);

        if ($i->enabled && $i->info('pull') && ! $i->canPull()) {
            return back()->with('warn', __(':name is on, but the key is missing, so leads cannot be fetched yet.', ['name' => $i->name()]));
        }

        return back()->with('ok', __(':name saved.', ['name' => $i->name()]));
    }

    public function fetch(string $platform, Puller $puller)
    {
        $i = LeadIntegration::for($platform);
        $r = $puller->pull($i);
        AuditLog::record('integration.fetch', $platform, ['ok' => $r['ok']]);
        if (! $r['ok']) {
            return back()->withErrors(['fetch' => __('Not fetched: :e', ['e' => $r['message']])]);
        }

        return back()->with('ok', __(':name: :new new leads, :old clients already in the CRM.', ['name' => $i->name(), 'new' => $r['counts']['created'], 'old' => $r['counts']['existing']]));
    }

    public function rotate(string $platform)
    {
        $i = LeadIntegration::for($platform);
        $config = $i->config ?? [];
        if ($platform === 'google_ads') {
            $config['google_key'] = Str::random(32);
        }
        $i->forceFill(['token' => Str::random(40), 'config' => $config])->save();
        AuditLog::record('integration.rotate', $platform);

        return back()->with('ok', __('New link created. Update it on :name, the old one has stopped working.', ['name' => $i->name()]));
    }

    /** Asks Facebook to send this Page's lead form leads to the CRM. */
    public function subscribeFacebook()
    {
        $i = LeadIntegration::for('facebook');
        $page = (string) $i->conf('page_id');
        if (! preg_match('/^\d{5,25}$/', $page) || ! $i->conf('page_token')) {
            return back()->withErrors(['facebook' => __('Enter the Page ID and Page access token first.')]);
        }
        $version = CompanySetting::current()?->wa('api_version') ?: 'v23.0';
        try {
            $res = Http::withToken((string) $i->conf('page_token'))->asForm()->timeout(20)
                ->post("https://graph.facebook.com/{$version}/{$page}/subscribed_apps", ['subscribed_fields' => 'leadgen']);
            if ($res->successful() && $res->json('success')) {
                AuditLog::record('integration.facebook_subscribe', $page);

                return back()->with('ok', __('Facebook Page connected. New lead form leads will now come into the CRM.'));
            }
            $msg = (string) ($res->json('error.message') ?? 'HTTP '.$res->status());
        } catch (Throwable $e) {
            $msg = $e->getMessage();
        }
        $i->failed('Facebook: '.$msg);

        return back()->withErrors(['facebook' => __('Facebook said: :e', ['e' => mb_substr($msg, 0, 200)])]);
    }
}
