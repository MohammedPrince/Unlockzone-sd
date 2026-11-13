<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Authentication and authorization checks live here, following the SunXFinder convention.
 */
class AuthRepository
{
    /**
     * Checks the credentials for one portal (admin or customer) and signs the user in.
     * Returns ['ok' => bool, 'message' => string, 'user' => ?User].
     */
    public function attempt(string $login, string $password, string $role, bool $remember = false): array
    {
        $user = User::where('role', $role)
            ->where(fn ($q) => $q->where('username', $login)->orWhere('email', $login))
            ->first();

        // Same message for "no such user" and "wrong password" so accounts cannot be probed.
        if (!$user || !Hash::check($password, $user->password)) {
            return ['ok' => false, 'message' => 'Invalid username or password.', 'user' => null];
        }

        if ($user->status === 'pending') {
            return ['ok' => false, 'message' => 'Your account is waiting for administrator activation.', 'user' => $user];
        }

        if ($user->status !== 'active') {
            return ['ok' => false, 'message' => 'Your account is suspended. Please contact support.', 'user' => $user];
        }

        Auth::login($user, $remember);
        $user->forceFill(['last_login_at' => now()])->save();

        return ['ok' => true, 'message' => 'Signed in.', 'user' => $user];
    }

    public function user(): ?User
    {
        return Auth::user();
    }

    /** True when the signed-in user is active and holds the given role. */
    public function check(string $role): bool
    {
        $user = Auth::user();

        return $user !== null && $user->role === $role && $user->isActive();
    }

    public function logout(): void
    {
        Auth::logout();
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }
}
