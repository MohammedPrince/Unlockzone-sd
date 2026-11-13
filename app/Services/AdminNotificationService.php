<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The admin's notification bell. It reads the activity log: everything customers, providers
 * and other administrators did, but not the admin's own actions and not routine sign-ins.
 */
class AdminNotificationService
{
    /** How each action is shown: title, colour, icon. Actions not listed here are not notified. */
    public const TYPES = [
        'customer_registered' => ['New customer registered', 'green', 'user'],
        'request_submitted' => ['New order', 'orange', 'cart'],
        'request_cancelled' => ['Order cancelled', 'muted', 'list'],
        'request_completed' => ['Order completed', 'green', 'list'],
        'request_failed' => ['Order failed', 'red', 'list'],
        'request_processing' => ['Order in process', 'blue', 'list'],
        'provider_order_error' => ['Provider error', 'red', 'plug'],
        'provider_order_sent' => ['Order sent to provider', 'blue', 'plug'],
        'login_failed' => ['Failed sign-in attempt', 'red', 'lock'],
        'password_reset' => ['Password reset', 'amber', 'lock'],
        'credit_recharge' => ['Credit recharged', 'green', 'wallet'],
        'credit_adjustment' => ['Credit adjusted', 'amber', 'wallet'],
        'customer_status' => ['Customer status changed', 'amber', 'users'],
        'service_saved' => ['Service changed', 'blue', 'box'],
        'settings_saved' => ['Settings changed', 'blue', 'cog'],
        'server_tool' => ['Server tool run', 'blue', 'tool'],
    ];

    private function query(User $admin): Builder
    {
        return ActivityLog::query()
            ->whereIn('action', array_keys(self::TYPES))
            // Leave out what this admin did themself; system and provider events have no user.
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $admin->id));
    }

    public function latest(User $admin, int $limit = 12): Collection
    {
        return $this->query($admin)->with('user')->orderByDesc('id')->limit($limit)->get();
    }

    public function unreadCount(User $admin): int
    {
        return min(99, $this->query($admin)
            ->when($admin->notifications_seen_at, fn ($q, $seen) => $q->where('created_at', '>', $seen))
            ->count());
    }

    public function markRead(User $admin): void
    {
        $admin->forceFill(['notifications_seen_at' => now()])->save();
    }

    public function isUnread(User $admin, ActivityLog $log): bool
    {
        return !$admin->notifications_seen_at || $log->created_at?->gt($admin->notifications_seen_at);
    }

    /** Where a click on the notification goes. */
    public function link(ActivityLog $log): ?string
    {
        return match ($log->subject_type) {
            'ServiceRequest' => $log->subject_id ? route('admin.requests.show', $log->subject_id) : null,
            'User' => $log->subject_id ? route('admin.customers.show', $log->subject_id) : null,
            'Service' => $log->subject_id ? route('admin.services.edit', $log->subject_id) : null,
            'ApiProvider' => route('admin.providers.index'),
            default => str_starts_with($log->action, 'credit_') ? route('admin.credits.index') : route('admin.activity', ['action' => $log->action]),
        };
    }
}
