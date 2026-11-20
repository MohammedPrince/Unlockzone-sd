<?php

namespace App\Support;

/**
 * Special price levels for customers: VIP and Web Owner. A customer is in one level at most.
 * Each level has its own percentage on the site price (a setting) and an optional fixed price per service.
 */
final class PriceTier
{
    public const VIP = 'vip';
    public const OWNER = 'owner';

    /** key => [label, users column, services column, percentage setting, customer tag] */
    private const TIERS = [
        self::VIP => ['VIP', 'is_vip', 'vip_price', 'vip_price_adjust', 'VIP'],
        self::OWNER => ['Web Owner', 'is_web_owner', 'owner_price', 'owner_price_adjust', 'Web Owner'],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::TIERS);
    }

    /** A known level from user input, VIP when missing or unknown. */
    public static function from(?string $tier): string
    {
        return isset(self::TIERS[$tier]) ? $tier : self::VIP;
    }

    public static function label(string $tier): string
    {
        return self::TIERS[$tier][0];
    }

    /** The boolean column on users. */
    public static function userColumn(string $tier): string
    {
        return self::TIERS[$tier][1];
    }

    /** The fixed-price column on services. */
    public static function priceColumn(string $tier): string
    {
        return self::TIERS[$tier][2];
    }

    /** The setting holding the level's percentage. */
    public static function adjustKey(string $tier): string
    {
        return self::TIERS[$tier][3];
    }

    /** The level whose customer tag this is ("VIP", "web owner"…), or null. */
    public static function forTag(?string $tag): ?string
    {
        $tag = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $tag)));
        foreach (self::TIERS as $key => $t) {
            if ($tag === mb_strtolower($t[4])) {
                return $key;
            }
        }

        return null;
    }
}
