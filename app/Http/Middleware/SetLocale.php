<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Applies the language picked in the top bar. The choice is kept in a cookie so it survives sign-out. */
class SetLocale
{
    public const SUPPORTED = ['en', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('locale');

        if (is_string($locale) && in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
