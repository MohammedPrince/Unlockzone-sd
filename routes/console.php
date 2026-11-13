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
