<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** After the admin resets a password, the person must choose their own before using the CRM. */
class ForcePasswordChange
{
    private const ALLOWED = ['profile', 'profile.password', 'logout', 'profile.language', 'profile.theme'];

    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if ($u && $u->must_change_password && ! in_array($request->route()?->getName(), self::ALLOWED, true)) {
            return redirect()->route('profile')->with('warn', __('Please choose your own new password before you continue.'));
        }

        return $next($request);
    }
}
