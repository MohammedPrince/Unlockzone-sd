<?php

namespace App\Http\Middleware;

use App\Services\SettingService;
use App\Support\MaintenanceSchedule;
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
    private const OPEN = ['admin', 'admin/*', 'language/*', 'provider-callback/*', 'logout', 'terms', 'privacy', 'ar/terms', 'ar/privacy', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        if (setting('maintenance_enabled') !== '1') {
            return $next($request);
        }

        // The launch time set in Settings has passed: the site reopens by itself, for this request too.
        if (app(SettingService::class)->endMaintenanceIfDue()) {
            return $next($request);
        }

        if ($request->is(...self::OPEN) || $request->user()?->isAdmin()) {
            return $next($request);
        }

        // Tells browsers and search engines when to come back: the launch time when set, otherwise in 10 minutes.
        $endsAt = MaintenanceSchedule::endsAt();
        $retry = (string) ($endsAt ? max(60, (int) now()->diffInSeconds($endsAt, false)) : 600);

        // Background calls from pages that were already open (live order status etc.).
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => 'The site is under maintenance. Please try again later.'], 503, ['Retry-After' => $retry]);
        }

        // Link-preview bots (WhatsApp, Telegram ...) skip pages that answer with an error, so they get the same
        // page with 200 and a shared link still shows its title and logo. Everyone else, search engines included, gets 503.
        $status = $this->isLinkPreviewBot($request) ? 200 : 503;

        return response()->view('maintenance', ['endsAt' => $endsAt], $status, ['Retry-After' => $retry]);
    }

    private function isLinkPreviewBot(Request $request): bool
    {
        return (bool) preg_match(
            '/WhatsApp|facebookexternalhit|Facebot|TelegramBot|Twitterbot|Slackbot|LinkedInBot|Discordbot|SkypeUriPreview|Viber|Pinterest|redditbot|Embedly/i',
            (string) $request->userAgent()
        );
    }
}
