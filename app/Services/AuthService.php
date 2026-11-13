<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\ActivityLogRepository;
use App\Repositories\AuthRepository;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthService
{
    public function __construct(
        private AuthRepository $auth,
        private ActivityLogRepository $logs,
    ) {
    }

    /** Sign in to one portal. $role decides which accounts are accepted there. */
    public function login(string $login, string $password, string $role, bool $remember): array
    {
        $result = $this->auth->attempt($login, $password, $role, $remember);

        if ($result['ok']) {
            $this->logs->log('login', "Signed in to the {$role} portal", $result['user'], [], $result['user']->id);
        } else {
            $this->logs->log('login_failed', "Failed {$role} sign-in for \"" . Str::limit($login, 60) . '"', null, [], $result['user']?->id);
        }

        return $result;
    }

    public function logout(): void
    {
        if ($user = $this->auth->user()) {
            $this->logs->log('logout', 'Signed out', $user, [], $user->id);
        }

        $this->auth->logout();
    }

    /** Emails a reset link. Always reports success so emails cannot be enumerated. */
    public function sendResetLink(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
    }

    /** Returns true when the token was valid and the password was changed. */
    public function resetPassword(array $data): bool
    {
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
            $this->logs->log('password_reset', 'Reset password by email link', $user, [], $user->id);
        });

        return $status === Password::PASSWORD_RESET;
    }
}
