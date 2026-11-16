<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\PasswordHelpRequested;
use App\Notifications\PasswordResetLink;
use App\Services\MailSettings;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Throwable;

/**
 * "Forgot password": a one-time link by email (60 minutes). Without email, the admins are told
 * and reset it from Users. The answer is always the same, so nobody can find out which emails exist.
 */
class PasswordResetController extends Controller
{
    public function showForgot()
    {
        return view('auth.forgot');
    }

    public function sendLink(Request $request)
    {
        $email = strtolower(trim((string) $request->validate(['email' => ['required', 'email', 'max:150']])['email']));
        $key = 'forgot|'.$email;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->with('ok', __('If this email has a login, a link to set a new password is on its way. If no email arrives in a few minutes, ask your admin.'));
        }
        RateLimiter::hit($key, 900);

        $user = User::where('email', $email)->where('active', true)->first();
        if ($user) {
            $sent = false;
            if (MailSettings::apply()) {
                try {
                    $status = Password::broker()->sendResetLink(['email' => $email], function (User $u, string $token) {
                        $u->notify(new PasswordResetLink($token));
                    });
                    $sent = $status === Password::RESET_LINK_SENT || $status === Password::RESET_THROTTLED;
                } catch (Throwable $e) {
                    Log::warning('Password reset email failed', ['user' => $user->id, 'error' => $e->getMessage()]);
                }
            }
            if (! $sent) {
                Notification::send(User::active()->where('role', 'admin')->where('id', '!=', $user->id)->get(), new PasswordHelpRequested($user));
            }
            AuditLog::record('password.forgot', 'user:'.$user->id, ['emailed' => $sent]);
        }

        return back()->with('ok', __('If this email has a login, a link to set a new password is on its way. If no email arrives in a few minutes, ask your admin.'));
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);
        $data['email'] = strtolower($data['email']);
        $user = User::where('email', $data['email'])->where('active', true)->first();
        if (! $user) {
            return back()->withErrors(['email' => __('This link is not valid any more. Ask for a new one.')]);
        }

        $status = Password::broker()->reset($data + ['password_confirmation' => $request->input('password_confirmation')], function (User $u, string $password) {
            $u->forceFill(['password' => $password, 'must_change_password' => false, 'remember_token' => null])->save();
            DB::table('sessions')->where('user_id', $u->id)->delete(); // logged out everywhere else
            event(new PasswordReset($u));
        });
        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __('This link is not valid any more. Ask for a new one.')]);
        }
        RateLimiter::clear('login|'.$data['email'].'|'.$request->ip());
        AuditLog::record('password.reset', 'user:'.$user->id);

        return redirect()->route('login')->with('ok', __('Your new password is set. Log in with it now.'));
    }

    /** Admin: email the person a reset link. */
    public function adminSend(Request $request, User $user)
    {
        abort_unless($request->user()->isAdmin(), 403);
        if (! $user->active) {
            return back()->withErrors(['password' => __('This person cannot log in (not active).')]);
        }
        if (! MailSettings::apply()) {
            return back()->withErrors(['password' => __('Email is not set up yet. Set it up in Company settings, or type a new password here instead.')]);
        }
        try {
            $user->notify(new PasswordResetLink(Password::broker()->createToken($user)));
        } catch (Throwable $e) {
            return back()->withErrors(['password' => __('The email could not be sent: :e', ['e' => mb_substr($e->getMessage(), 0, 150)])]);
        }
        AuditLog::record('password.link_sent', 'user:'.$user->id);

        return back()->with('ok', __('A link to set a new password was emailed to :email.', ['email' => $user->email]));
    }
}
