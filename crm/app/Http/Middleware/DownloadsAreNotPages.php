<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A downloaded file (Excel, PDF, receipt…) is not a page: it must not become the "previous page"
 * that Save / Approve buttons send the person back to. Without this, opening a payslip PDF in a
 * new tab and then pressing Save would download the PDF again instead of showing the page.
 */
class DownloadsAreNotPages
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $type = (string) $response->headers->get('Content-Type', '');
        $isPage = $response->isRedirection() || $type === '' || str_starts_with($type, 'text/html');
        if ($request->isMethod('GET') && (! $isPage || $response->headers->has('Content-Disposition'))) {
            $request->setRouteResolver(fn () => null); // the session then keeps the real previous page
        }

        return $response;
    }
}
