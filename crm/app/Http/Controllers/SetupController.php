<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/** First run: the pharma company enters its own name and creates the first admin. */
class SetupController extends Controller
{
    public function show()
    {
        return view('setup', ['codeMissing' => strlen((string) config('crm.setup_code')) < 8]);
    }

    public function store(Request $request)
    {
        $code = (string) config('crm.setup_code');
        if (strlen($code) < 8) {
            return back()->withErrors(['setup_code' => __('First write CRM_SETUP_CODE (8 or more characters) in the .env file on the server.')]);
        }
        if (! hash_equals($code, (string) $request->input('setup_code'))) {
            return back()->withErrors(['setup_code' => __('The setup code is not correct.')])->withInput($request->except(['password', 'password_confirmation', 'setup_code']));
        }
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'office_start' => ['required', 'date_format:H:i'],
            'office_end' => ['required', 'date_format:H:i', 'after:office_start'],
            'lunch_start' => ['required', 'date_format:H:i'],
            'lunch_end' => ['required', 'date_format:H:i', 'after:lunch_start'],
            'admin_name' => ['required', 'string', 'max:100'],
            'admin_email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $user = DB::transaction(function () use ($data) {
            CompanySetting::create(collect($data)->only(['company_name', 'gstin', 'phone', 'address', 'office_start', 'office_end', 'lunch_start', 'lunch_end'])->all());

            return User::create([
                'name' => $data['admin_name'],
                'email' => strtolower($data['admin_email']),
                'password' => $data['password'],
                'role' => 'admin',
            ]);
        });
        CompanySetting::forget();
        Auth::login($user);
        $request->session()->regenerate();
        AuditLog::record('setup.done', $data['company_name']);

        return redirect()->route('dashboard')->with('ok', __('Setup complete. Next, add your team under Users.'));
    }
}
