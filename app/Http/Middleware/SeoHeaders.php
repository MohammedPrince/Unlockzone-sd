<?php

namespace App\Http\Middleware;

use App\Support\Seo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Keeps everything that is not a public page (admin, customer area, password links, the dev site) out of search results. */
class SeoHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!Seo::indexable($request)) {
            $response->headers->set('X-Robots-Tag', Seo::privateHost($request) ? 'noindex, nofollow' : 'noindex');
        }

        return $response;
    }
}
