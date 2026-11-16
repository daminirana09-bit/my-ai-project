<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Laravel's "throttle:10,1" counts every throttled page together for one person (or one IP).
 * An office shares one IP, so 10 logins at 9:30 would block "Forgot password", and 10 WhatsApp
 * replies would block an export. Here each page has its own count, per person or per IP.
 */
class ThrottlePerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();
        $page = $request->method().'|'.($route?->getDomain() ?? '').'|'.($route?->uri() ?? $request->path());
        if ($user = $request->user()) {
            return sha1($page.'|u|'.$user->getAuthIdentifier());
        }

        return sha1($page.'|ip|'.$request->ip());
    }
}
