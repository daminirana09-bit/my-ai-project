<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
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
        $email = strtolower($data['email']);
        // Locks one email from one place after 5 wrong tries. The whole office shares one IP,
        // so a plain per-IP limit would block everyone at 9:30 in the morning.
        $key = 'login|'.$email.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => __('Too many wrong tries. Try again in :s seconds.', ['s' => RateLimiter::availableIn($key)])])->onlyInput('email');
        }
        $user = User::where('email', $email)->first();

        if (! $user || ! $user->active || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            AuditLog::record('login.failed', $email);

            return back()->withErrors(['email' => __('Wrong email or password.')])->onlyInput('email');
        }
        RateLimiter::clear($key);

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
        $key = 'otp|'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => __('Too many wrong tries. Try again in :s seconds.', ['s' => RateLimiter::availableIn($key)])]);
        }

        if (! (new Google2FA)->verifyKey($user->two_factor_secret, $request->input('code'), 1)) {
            RateLimiter::hit($key, 300);
            AuditLog::record('login.otp_failed', $user->email);

            return back()->withErrors(['code' => __('The code is wrong or has expired.')]);
        }
        $request->session()->forget('2fa:user');
        RateLimiter::clear($key);

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
