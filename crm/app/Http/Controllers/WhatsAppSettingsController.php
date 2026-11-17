<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\User;
use App\Models\WhatsappNumber;
use App\Services\Translation\TranslationFailed;
use App\Services\Translation\Translator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Admin connects the company's WhatsApp Business account and gives each salesperson their number. */
class WhatsAppSettingsController extends Controller
{
    public function edit()
    {
        $c = CompanySetting::current();
        if (! $c->wa('verify_token')) {
            $c->update(['whatsapp_config' => array_merge($c->whatsapp_config ?? [], ['verify_token' => Str::random(32)])]);
        }
        if (! $c->lead_capture_token) {
            $c->update(['lead_capture_token' => Str::random(40)]);
        }
        CompanySetting::forget();

        return view('whatsapp-settings', [
            'c' => CompanySetting::current(),
            'numbers' => WhatsappNumber::with('user:id,name')->orderBy('label')->get(),
            'people' => User::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'access_token' => ['nullable', 'string', 'max:1000'],
            'app_secret' => ['nullable', 'string', 'max:200'],
            'api_version' => ['required', 'regex:/^v\d{2}\.\d$/'],
        ]);
        $c = CompanySetting::current();
        $cfg = $c->whatsapp_config ?? [];
        // Empty boxes keep the saved secret: it is never shown back on screen.
        foreach (['access_token', 'app_secret'] as $k) {
            if (filled($data[$k] ?? null)) {
                $cfg[$k] = trim($data[$k]);
            }
        }
        $cfg['api_version'] = $data['api_version'];
        $c->update(['whatsapp_config' => $cfg]);
        CompanySetting::forget();
        AuditLog::record('whatsapp.settings');

        return back()->with('ok', __('WhatsApp settings saved.'));
    }

    public function storeNumber(Request $request)
    {
        $data = $this->validatedNumber($request, null);
        WhatsappNumber::create($data);
        AuditLog::record('whatsapp.number_add', $data['display_phone']);

        return back()->with('ok', __('Number added.'));
    }

    public function updateNumber(Request $request, WhatsappNumber $number)
    {
        $number->update($this->validatedNumber($request, $number));
        AuditLog::record('whatsapp.number_update', $number->display_phone);

        return back()->with('ok', __('Saved.'));
    }

    /** Chat translation: which service, its keys (kept encrypted, never shown again), automatic or on request. */
    public function updateTranslation(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(array_keys(Translator::PROVIDERS))],
            'auto' => ['nullable', 'boolean'],
            'bhashini_user' => ['nullable', 'string', 'max:100'],
            'bhashini_key' => ['nullable', 'string', 'max:300'],
            'bhashini_pipeline' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9]+$/'],
            'google_key' => ['nullable', 'string', 'max:300'],
            'ms_key' => ['nullable', 'string', 'max:300'],
            'ms_region' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9]+$/'],
        ]);
        $c = CompanySetting::current();
        $cfg = $c->translation_config ?? [];
        $cfg['provider'] = $data['provider'];
        $cfg['auto'] = $request->boolean('auto');
        foreach (['bhashini_user', 'bhashini_pipeline', 'ms_region'] as $k) {
            $cfg[$k] = trim((string) ($data[$k] ?? '')) ?: null;
        }
        foreach (['bhashini_key', 'google_key', 'ms_key'] as $k) {
            if (filled($data[$k] ?? null)) {
                $cfg[$k] = trim($data[$k]); // empty = keep the saved key
            }
        }
        $c->update(['translation_config' => $cfg]);
        CompanySetting::forget();
        AuditLog::record('translation.settings', null, ['provider' => $cfg['provider']]);

        return back()->with('ok', __('Translation settings saved. Use "Test" to check them.'));
    }

    public function testTranslation(Request $request)
    {
        $lang = $request->validate(['lang' => ['required', Rule::in(array_keys(\App\Services\WhatsApp\LanguageDetector::NAMES))]])['lang'];
        $tr = app(Translator::class);
        if (! $tr->enabled()) {
            return back()->withErrors(['translation' => __('Choose a service and enter its key first.')]);
        }
        try {
            $r = $tr->translate('Thank you for your order. We will dispatch it tomorrow.', 'en', $lang);
        } catch (TranslationFailed $e) {
            return back()->withErrors(['translation' => __('Translation: :e', ['e' => $e->getMessage()])]);
        }

        return back()->with('ok', __('Test worked: :t', ['t' => $r['text']]));
    }

    public function rotateToken()
    {
        CompanySetting::current()->update(['lead_capture_token' => Str::random(40)]);
        CompanySetting::forget();
        AuditLog::record('lead_capture.rotate');

        return back()->with('ok', __('New website form link created. Update it on your website.'));
    }

    private function validatedNumber(Request $request, ?WhatsappNumber $n): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'display_phone' => ['required', 'string', 'max:20'],
            'phone_number_id' => ['required', 'regex:/^\d{6,30}$/', Rule::unique('whatsapp_numbers')->ignore($n?->id)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
