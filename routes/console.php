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

// Database backup: the same as Admin > Server Tools > "Database backup". The file is listed there and
// downloads from /admin/tools/backups/backup-YYYY-MM-DD-HHMMSS.sql (admins only), on DEV and PROD alike.
Artisan::command('db:backup', function () {
    $this->info(app(\App\Services\ServerToolService::class)->backup());
    app(\App\Repositories\ActivityLogRepository::class)->log('server_tool', 'Weekly database backup', null, ['tool' => 'backup', 'scheduled' => true]);
})->purpose('Back up the database (listed in Admin > Server Tools)');

// Once a week, at 00:00 (Sunday by default, on the BACKUP_TIMEZONE clock).
\Illuminate\Support\Facades\Schedule::command('db:backup')
    ->weeklyOn((int) config('backup.day'), (string) config('backup.time'))
    ->timezone((string) config('backup.timezone'))
    ->withoutOverlapping(120)
    ->onFailure(fn () => \Illuminate\Support\Facades\Log::error('Weekly database backup failed (php artisan db:backup).'));
