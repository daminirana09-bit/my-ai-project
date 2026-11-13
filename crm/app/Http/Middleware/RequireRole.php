<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** role:admin → admins only. role:manage → admins and managers. */
class RequireRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $u = $request->user();
        $ok = $u && ($u->active ?? true) && match ($role) {
            'admin' => $u->isAdmin(),
            'manage' => $u->canManage(),
            default => false,
        };
        abort_unless($ok, 403, __('You do not have access to this page.'));

        return $next($request);
    }
}
