<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * The optional "back online at" time of maintenance mode (Admin > Settings > Maintenance mode).
 * Stored as the local date and time the admin typed plus their browser's time zone, so "00:00" means
 * midnight where the admin is, whatever time zone the server runs in.
 */
class MaintenanceSchedule
{
    /** The launch moment, or null when none is set (or it cannot be read). */
    public static function endsAt(): ?Carbon
    {
        return self::parse((string) setting('maintenance_ends_at'), (string) setting('maintenance_ends_tz'));
    }

    public static function parse(?string $local, ?string $tz): ?Carbon
    {
        if (blank($local)) {
            return null;
        }

        try {
            return Carbon::parse($local, self::zone($tz));
        } catch (Throwable) {
            return null;
        }
    }

    /** A valid time zone name, or the app's own. */
    public static function zone(?string $tz): string
    {
        return filled($tz) && in_array($tz, timezone_identifiers_list(), true) ? $tz : config('app.timezone');
    }
}
