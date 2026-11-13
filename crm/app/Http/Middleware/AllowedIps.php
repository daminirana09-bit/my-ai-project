<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Rule 1: the CRM screens open only from the office (or VPN) IP addresses in CRM_ALLOWED_IPS.
 * Empty means no limit. The WhatsApp webhook (added later) is outside this list on purpose.
 */
class AllowedIps
{
    public function handle(Request $request, Closure $next)
    {
        $allowed = array_filter(array_map('trim', explode(',', (string) config('crm.allowed_ips'))));
        if ($allowed && ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            abort(403, 'This CRM can only be opened from the office network.');
        }

        return $next($request);
    }
}
