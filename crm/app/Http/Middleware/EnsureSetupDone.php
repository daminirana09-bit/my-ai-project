<?php

namespace App\Http\Middleware;

use App\Models\CompanySetting;
use Closure;
use Illuminate\Http\Request;

/** Until a company has entered its own details, every page goes to the setup wizard. */
class EnsureSetupDone
{
    public function handle(Request $request, Closure $next)
    {
        $done = CompanySetting::current() !== null;
        if (! $done && ! $request->routeIs('setup.*')) {
            return redirect()->route('setup.show');
        }
        if ($done && $request->routeIs('setup.*')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
