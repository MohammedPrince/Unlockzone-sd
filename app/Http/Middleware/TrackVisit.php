<?php

namespace App\Http\Middleware;

use App\Models\Visit;
use App\Services\VisitSourceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts one visit per browser session on the customer site and remembers where it came from,
 * so the admin dashboard can show visitors by source and which source brought each sign-up.
 * Admins, the admin area, bots and background requests are not counted.
 */
class TrackVisit
{
    public const COOKIE = 'vid';

    public function __construct(private VisitSourceService $sources)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->record($request);
        } catch (Throwable $e) {
            // Counting visitors must never break a page.
            report($e);
        }

        return $response;
    }

    private function record(Request $request): void
    {
        if (!$request->isMethod('GET') || $request->ajax() || $request->expectsJson() || !$request->hasSession()
            || $request->is('admin', 'admin/*', 'up', 'language/*') || $request->user()?->isAdmin()
            || $this->sources->isBot($request->userAgent())) {
            return;
        }

        $session = $request->session();
        if ($session->has('visit_id')) {
            // Already counted. Link the visit to the customer once they sign in.
            if ($request->user() && !$session->get('visit_linked')) {
                Visit::whereKey($session->get('visit_id'))->whereNull('user_id')->update(['user_id' => $request->user()->id]);
                $session->put('visit_linked', true);
            }

            return;
        }

        $visitorId = $request->cookie(self::COOKIE);
        if (!is_string($visitorId) || !preg_match('/^[A-Za-z0-9]{20,40}$/', $visitorId)) {
            $visitorId = Str::random(32);
        }
        Cookie::queue(self::COOKIE, $visitorId, 60 * 24 * 365 * 2);

        [$source, $host] = $this->sources->detect($request);

        // A fresh session within 30 minutes of the last visit (e.g. after signing out) is the same visit,
        // unless the visitor just arrived again from a new, known source.
        $recent = Visit::where('visitor_id', $visitorId)->where('created_at', '>=', now()->subMinutes(30))->latest('id')->first();
        if ($recent && ($source === 'direct' || $source === $recent->source)) {
            $session->put('visit_id', $recent->id);
            $session->put('visit_source', $recent->source);
            $session->put('visit_linked', $recent->user_id !== null);

            return;
        }

        $visit = Visit::create([
            'visitor_id' => $visitorId,
            'source' => $source,
            'referrer_host' => $host,
            'landing_path' => mb_substr('/' . ltrim($request->path(), '/'), 0, 191),
            'user_id' => $request->user()?->id,
        ]);

        $session->put('visit_id', $visit->id);
        $session->put('visit_source', $source);
        $session->put('visit_linked', $request->user() !== null);
    }
}
