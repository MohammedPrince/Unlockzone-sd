<?php

/*
 * Weekly database backup (php artisan db:backup, run by the scheduler). The backups are the ones in
 * Admin > Server Tools > Database backups, downloadable at /admin/tools/backups/{file}.
 * Change these in .env if needed.
 */
return [
    // Day of the week (0 = Sunday … 6 = Saturday) and time of the weekly backup.
    'day' => (int) env('BACKUP_DAY', 0),
    'time' => env('BACKUP_TIME', '00:00'),

    // The clock the backup time and the file names follow.
    'timezone' => env('BACKUP_TIMEZONE', 'Africa/Khartoum'),
];
