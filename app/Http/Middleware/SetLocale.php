<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the language picked in the top bar. The choice is kept in a cookie so it survives sign-out.
 * An Arabic address (/ar/...) is always Arabic, and remembers it for the pages opened from there.
 */
class SetLocale
{
    public const SUPPORTED = ['en', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('locale');

        if ($request->is('ar', 'ar/*')) {
            if ($locale !== 'ar') {
                Cookie::queue('locale', 'ar', 60 * 24 * 365);
            }
            $locale = 'ar';
        }

        if (is_string($locale) && in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
