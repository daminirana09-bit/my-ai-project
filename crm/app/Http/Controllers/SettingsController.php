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
