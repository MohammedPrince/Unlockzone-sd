<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Our DHRU Fusion API for other servers: /api/index.php (no session, no CSRF).
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['role' => EnsureRole::class]);
        $middleware->web(append: [\App\Http\Middleware\SetLocale::class, \App\Http\Middleware\TrackVisit::class]);

        // Providers post order updates here from their own servers (no session, no CSRF token).
        $middleware->validateCsrfTokens(except: ['provider-callback/*']);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.login') : route('login'));

        // Already signed in and opening a login/register page: go to your own dashboard.
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isAdmin() ? route('admin.dashboard') : route('customer.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
