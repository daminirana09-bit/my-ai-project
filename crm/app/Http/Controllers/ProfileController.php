<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use PragmaRX\Google2FA\Google2FA;

/** Language, password and 2-step login for the signed-in person. */
class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $pending = $request->session()->get('2fa:pending');

        return view('profile', [
            'pendingSecret' => $pending,
            'otpUri' => $pending ? (new Google2FA)->getQRCodeUrl(\App\Models\CompanySetting::current()->company_name, $request->user()->email, $pending) : null,
        ]);
    }

    public function language(Request $request)
    {
        $data = $request->validate(['locale' => ['required', Rule::in(array_keys(User::LOCALES))]]);
        $request->user()->update($data);

        return back();
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        $request->user()->update(['password' => $request->password]);
        AuditLog::record('profile.password');

        return back()->with('ok', __('Password changed.'));
    }

    public function startTwoFactor(Request $request)
    {
        $request->session()->put('2fa:pending', (new Google2FA)->generateSecretKey(32));

        return back();
    }

    public function confirmTwoFactor(Request $request)
    {
        $secret = $request->session()->get('2fa:pending');
        $request->validate(['code' => ['required', 'digits:6']]);
        if (! $secret || ! (new Google2FA)->verifyKey($secret, $request->code, 1)) {
            return back()->withErrors(['code' => __('The code is wrong or has expired.')]);
        }
        $request->user()->forceFill(['two_factor_secret' => $secret])->save();
        $request->session()->forget('2fa:pending');
        AuditLog::record('profile.2fa_on');

        return back()->with('ok', __('2-step login is on.'));
    }

    public function disableTwoFactor(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $request->user()->forceFill(['two_factor_secret' => null])->save();
        AuditLog::record('profile.2fa_off');

        return back()->with('ok', __('2-step login is off.'));
    }
}
