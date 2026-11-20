<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\User;
use App\Repositories\ActivityLogRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Admin > Administrators: admins add, suspend and remove other admins.
 * An admin can never lock themself out, and the last active admin always stays.
 */
class AdministratorService
{
    public function __construct(private ActivityLogRepository $logs)
    {
    }

    /** @return Collection<int, User> */
    public function all(): Collection
    {
        return User::where('role', User::ROLE_ADMIN)->orderBy('created_at')->get();
    }

    public function find(int $id): User
    {
        return User::where('role', User::ROLE_ADMIN)->findOrFail($id);
    }

    public function create(array $data, int $actorId): User
    {
        $name = trim($data['first_name'] . ' ' . ($data['last_name'] ?? ''));
        $admin = new User([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'name' => $name,
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
        ]);
        // Not fillable on purpose: set here only.
        $admin->role = User::ROLE_ADMIN;
        $admin->status = 'active';
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->logs->log('admin_created', "Added administrator {$admin->username}", $admin, [], $actorId);

        return $admin;
    }

    /** Name, username, email, phone; the password only when a new one is given. */
    public function update(User $admin, array $data, int $actorId): void
    {
        $admin->fill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'name' => trim($data['first_name'] . ' ' . ($data['last_name'] ?? '')),
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ]);
        if (filled($data['password'] ?? null)) {
            $admin->password = $data['password'];
        }
        $changed = array_keys($admin->getDirty());
        $admin->save();

        // Only the field names are logged, never the values (the password hash stays out of the log).
        $this->logs->log('admin_updated', "Updated administrator {$admin->username}", $admin, ['changed' => $changed], $actorId);
    }

    /** Active or suspended. A suspended admin cannot sign in. */
    public function setActive(User $admin, bool $active, int $actorId): void
    {
        if (!$active) {
            $this->guard($admin, $actorId, 'suspend');
        }

        $admin->forceFill(['status' => $active ? 'active' : 'suspended'])->save();
        $this->logs->log('admin_status', ($active ? 'Activated' : 'Suspended') . " administrator {$admin->username}", $admin, [], $actorId);
    }

    public function delete(User $admin, int $actorId): void
    {
        $this->guard($admin, $actorId, 'delete');

        $username = $admin->username;
        $admin->delete();
        $this->logs->log('admin_removed', "Deleted administrator {$username}", null, ['username' => $username], $actorId);
    }

    private function guard(User $admin, int $actorId, string $what): void
    {
        if ($admin->id === $actorId) {
            throw new WorkflowException(__("You cannot :action your own account.", ['action' => __($what)]));
        }

        $otherActive = User::where('role', User::ROLE_ADMIN)->where('status', 'active')->whereKeyNot($admin->id)->exists();
        if (!$otherActive) {
            throw new WorkflowException(__('At least one active administrator must stay.'));
        }
    }
}
