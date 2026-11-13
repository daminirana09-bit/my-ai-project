<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Each person sees the app in their own language: English, Hinglish or Hindi. */
class SetUserLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->user()?->locale ?? $request->session()->get('locale', 'en');
        app()->setLocale(in_array($locale, ['en', 'hing', 'hi'], true) ? $locale : 'en');

        return $next($request);
    }
}
