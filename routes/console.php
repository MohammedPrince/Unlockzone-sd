<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Asks connected providers about open orders. Needs the scheduler: `php artisan schedule:work`
// (or a cron / Windows task running `php artisan schedule:run` every minute).
Artisan::command('providers:sync-orders {--limit=100}', function () {
    $checked = app(\App\Services\ProviderOrderService::class)->syncOpen((int) $this->option('limit'));
    $this->info("Checked {$checked} open provider orders.");
})->purpose('Fetch results for orders that were sent to connected providers');

\Illuminate\Support\Facades\Schedule::command('providers:sync-orders')->everyMinute()->withoutOverlapping();

// Maintenance mode with a "Back online at" time: switched off at that minute even if nobody visits the site.
// (Any request after the launch time switches it off too, so this is only a backstop.)
Artisan::command('maintenance:check-launch', function () {
    $this->info(app(\App\Services\SettingService::class)->endMaintenanceIfDue()
        ? 'Launch time reached: maintenance mode is now off.'
        : 'Nothing to do.');
})->purpose('Turn maintenance mode off once its launch time has passed');

\Illuminate\Support\Facades\Schedule::command('maintenance:check-launch')->everyMinute();
