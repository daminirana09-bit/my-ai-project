<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession as BaseStartSession;

/**
 * Laravel remembers the last GET page so that back() can return to it. A file download,
 * a PDF or an image (the company logo) is not a page: if it were remembered, the next
 * form would "go back" to the file instead of the screen the user was on.
 */
class StartSession extends BaseStartSession
{
    /** Routes that return a file, never a page. */
    public const NOT_PAGES = ['logo', 'leads.export', 'leads.template', 'products.export', 'quotes.pdf'];

    protected function storeCurrentUrl(Request $request, $session)
    {
        if (in_array($request->route()?->getName(), self::NOT_PAGES, true)) {
            return;
        }
        $dest = $request->headers->get('Sec-Fetch-Dest');
        if ($dest !== null && ! in_array($dest, ['document', 'empty'], true)) {
            return; // image, script, style… loaded by a page
        }

        parent::storeCurrentUrl($request, $session);
    }
}
