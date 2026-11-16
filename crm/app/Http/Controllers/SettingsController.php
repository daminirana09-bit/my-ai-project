<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Each company manages its own name, details, logo, office and lunch timing. */
class SettingsController extends Controller
{
    public function edit()
    {
        return view('settings', ['c' => CompanySetting::current()]);
    }

    public function update(Request $request)
    {
        $c = CompanySetting::current();
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'drug_licence_no' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ]);

        if ($request->hasFile('logo')) {
            // Kept outside the public folder and served through the app.
            $data['logo_path'] = $request->file('logo')->store('branding', 'local');
            if ($c->logo_path) {
                Storage::disk('local')->delete($c->logo_path);
            }
        }
        unset($data['logo']);
        $c->update($data);
        CompanySetting::forget();
        AuditLog::record('settings.update');

        return back()->with('ok', __('Settings saved.'));
    }

    /** The company's own mailbox for "forgot password" emails. */
    public function updateMail(Request $request)
    {
        $data = $request->validate([
            'mail_host' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9.\-]+$/i'],
            'mail_port' => ['nullable', 'integer', 'in:465,587'],
            'mail_username' => ['nullable', 'email', 'max:150'],
            'mail_password' => ['nullable', 'string', 'max:200'],
            'mail_from' => ['nullable', 'email', 'max:150'],
        ], ['mail_host.regex' => __('Write only the server name, e.g. smtp.hostinger.com')]);
        $c = CompanySetting::current();
        $host = strtolower(trim((string) ($data['mail_host'] ?? '')));
        if ($host !== '') {
            try {
                \App\Services\Leads\Puller::assertSafeUrl('https://'.$host.'/');
            } catch (\App\Services\Leads\PullFailed) {
                return back()->withErrors(['mail_host' => __('The email server address is not reachable or not allowed.')])->withInput();
            }
        }
        $cfg = $c->mail_config ?? [];
        $cfg['host'] = $host ?: null;
        $cfg['port'] = (string) ($data['mail_port'] ?? 465);
        $cfg['username'] = $data['mail_username'] ?? null;
        $cfg['from'] = $data['mail_from'] ?? null;
        if (filled($data['mail_password'] ?? null)) {
            $cfg['password'] = $data['mail_password']; // empty = keep the saved one
        }
        $c->update(['mail_config' => $cfg]);
        CompanySetting::forget();
        AuditLog::record('settings.mail');

        return back()->with('ok', __('Email settings saved. Send a test email to check them.'));
    }

    public function testMail(Request $request)
    {
        if (! \App\Services\MailSettings::apply()) {
            return back()->withErrors(['mail_host' => __('Fill in the email server, address and password first.')]);
        }
        try {
            \Illuminate\Support\Facades\Mail::raw(__('This is a test from your sales CRM. Email works, so "Forgot password" links will arrive.'), function ($m) use ($request) {
                $m->to($request->user()->email)->subject(__('Test email from your sales CRM'));
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['mail_host' => __('The email could not be sent: :e', ['e' => mb_substr($e->getMessage(), 0, 200)])]);
        }
        AuditLog::record('settings.mail_test');

        return back()->with('ok', __('Test email sent to :email. Check the inbox (and spam).', ['email' => $request->user()->email]));
    }

    public function officeHours()
    {
        return view('office-hours', ['c' => CompanySetting::current(), 'people' => \App\Models\User::active()->orderBy('name')->get()]);
    }

    public function updateOfficeHours(Request $request)
    {
        $data = $request->validate([
            'office_start' => ['required', 'date_format:H:i'],
            'office_end' => ['required', 'date_format:H:i', 'after:office_start'],
            'lunch_start' => ['required', 'date_format:H:i'],
            'lunch_end' => ['required', 'date_format:H:i', 'after:lunch_start'],
            'late_grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'full_day_hours' => ['required', 'numeric', 'min:1', 'max:16'],
            'half_day_hours' => ['required', 'numeric', 'min:0.5', 'max:12', 'lt:full_day_hours'],
            'weekly_off' => ['nullable', 'array'],
            'weekly_off.*' => ['integer', 'between:0,6'],
        ]);
        $data['full_day_minutes'] = (int) round($data['full_day_hours'] * 60);
        $data['half_day_minutes'] = (int) round($data['half_day_hours'] * 60);
        $data['weekly_off'] = implode(',', $data['weekly_off'] ?? []);
        unset($data['full_day_hours'], $data['half_day_hours']);
        CompanySetting::current()->update($data);
        CompanySetting::forget();
        AuditLog::record('office_hours.update');

        return back()->with('ok', __('Office hours saved.'));
    }

    public function logo()
    {
        $c = CompanySetting::current();
        abort_unless($c?->logo_path && Storage::disk('local')->exists($c->logo_path), 404);

        return response()->file(Storage::disk('local')->path($c->logo_path), ['Cache-Control' => 'private, max-age=3600']);
    }
}
