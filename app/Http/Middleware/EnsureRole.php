<?php

namespace App\Http\Middleware;

use App\Repositories\AuthRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Gate for a whole portal: `role:admin` or `role:customer`. The actual check lives in AuthRepository. */
class EnsureRole
{
    public function __construct(private AuthRepository $auth)
    {
    }

    public function handle(Request $request, Closure $next, string $role): Response
    {
        $loginRoute = $role === 'admin' ? 'admin.login' : 'login';
        $user = $this->auth->user();

        if (!$user) {
            return redirect()->guest(route($loginRoute));
        }

        // Signed in to the other portal: send them home rather than showing a login form.
        if ($user->role !== $role) {
            return redirect()->route($user->isAdmin() ? 'admin.dashboard' : 'customer.dashboard');
        }

        // Suspended while signed in: end the session on the next click.
        if (!$this->auth->check($role)) {
            $this->auth->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route($loginRoute)->withErrors(['login' => 'Your account is not active. Please contact support.']);
        }

        return $next($request);
    }
}
