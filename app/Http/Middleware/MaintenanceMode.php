<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maintenance mode (Admin > Settings): visitors and customers get the maintenance page instead of the site.
 * Signed-in admins see everything as usual, and the admin sign-in page stays open so they can get in.
 * Provider callbacks and the server API (routes/api.php, not in the web group) keep working.
 */
class MaintenanceMode
{
    /** Paths that stay open while maintenance is on. */
    private const OPEN = ['admin', 'admin/*', 'language/*', 'provider-callback/*', 'logout', 'terms', 'privacy', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        if (setting('maintenance_enabled') !== '1' || $request->is(...self::OPEN) || $request->user()?->isAdmin()) {
            return $next($request);
        }

        // Background calls from pages that were already open (live order status etc.).
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => 'The site is under maintenance. Please try again later.'], 503, ['Retry-After' => '600']);
        }

        return response()->view('maintenance', [], 503, ['Retry-After' => '600']);
    }
}
