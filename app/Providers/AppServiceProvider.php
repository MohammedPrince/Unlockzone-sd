<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One instance per request so settings are read from the database once.
        $this->app->singleton(\App\Repositories\SettingRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::defaultView('partials.pagination');

        // Numbers the panel layout shows on every page: pending requests (admin) or own balance (customer).
        \Illuminate\Support\Facades\View::composer('layouts.panel', function ($view) {
            $user = auth()->user();

            if ($user?->isAdmin()) {
                // Orders waiting for the admin: new orders start as "processing", so count both open statuses.
                $openCounts = app(\App\Repositories\ServiceRequestRepository::class)->statusCounts();
                $view->with('navPending', $openCounts['pending'] + $openCounts['processing']);

                $notifications = app(\App\Services\AdminNotificationService::class);
                $view->with('notifUnread', $notifications->unreadCount($user));
                $view->with('notifItems', $notifications->latest($user));
            } elseif ($user) {
                $view->with('navBalance', app(\App\Repositories\CreditRepository::class)->balance($user->id));

                // Menu: "Place an order" / "Order history" list the categories that have services on sale.
                $view->with('navCategories', \App\Models\ServiceCategory::where('status', 'active')
                    ->whereHas('services', fn ($q) => $q->where('status', 'active'))
                    ->orderBy('sort_order')->orderBy('name')
                    ->get(['id', 'name']));

                // "Last login": the sign-in before this one (the newest row is the current session).
                $view->with('navLastLogin', \App\Models\ActivityLog::where('user_id', $user->id)->where('action', 'login')
                    ->orderByDesc('id')->skip(1)->first(['created_at', 'ip_address']));

                // The scrolling bar: the administrator's messages, then the newest services.
                if (setting('ticker_enabled') === '1') {
                    $view->with('tickerItems', \App\Models\Announcement::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get());
                    $view->with('tickerServices', setting('ticker_new_services') === '1'
                        ? app(\App\Repositories\ServiceRepository::class)->recentlyAdded((int) setting('new_badge_days', 7))
                        : collect());
                }
            }
        });
        // utf8mb4 index length on older MySQL / MariaDB builds.
        \Illuminate\Support\Facades\Schema::defaultStringLength(191);
    }
}
