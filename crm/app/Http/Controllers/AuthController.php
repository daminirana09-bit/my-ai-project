<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', strtolower($data['email']))->first();

        if (! $user || ! $user->active || ! Hash::check($data['password'], $user->password)) {
            AuditLog::record('login.failed', strtolower($data['email']));

            return back()->withErrors(['email' => __('Wrong email or password.')])->onlyInput('email');
        }

        if ($user->two_factor_secret) {
            $request->session()->put('2fa:user', $user->id);

            return redirect()->route('login.otp');
        }

        return $this->finish($request, $user);
    }

    public function showOtp(Request $request)
    {
        abort_unless($request->session()->has('2fa:user'), 403);

        return view('auth.otp');
    }

    public function verifyOtp(Request $request)
    {
        $user = User::find($request->session()->get('2fa:user'));
        abort_unless($user && $user->active, 403);
        $request->validate(['code' => ['required', 'digits:6']]);

        if (! (new Google2FA)->verifyKey($user->two_factor_secret, $request->input('code'), 1)) {
            AuditLog::record('login.otp_failed', $user->email);

            return back()->withErrors(['code' => __('The code is wrong or has expired.')]);
        }
        $request->session()->forget('2fa:user');

        return $this->finish($request, $user);
    }

    private function finish(Request $request, User $user)
    {
        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        AuditLog::record('login', $user->email);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        AuditLog::record('logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
