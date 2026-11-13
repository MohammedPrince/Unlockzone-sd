<?php

namespace App\Repositories;

use App\Models\Setting;
use Throwable;

/** Key/value platform settings. Registered as a singleton so one query serves the whole request. */
class SettingRepository
{
    public const DEFAULTS = [
        'site_name' => 'DHRU Server',
        'allow_registration' => '1',
        'new_account_status' => 'active',
        'support_contact' => '',
        'announcement' => '',
        // Days a service keeps its "New" tag (0 = never).
        'new_badge_days' => '7',
        // Floating chat buttons on the customer site.
        'whatsapp_enabled' => '0',
        'whatsapp_number' => '',
        'telegram_enabled' => '0',
        'telegram_username' => '',
        // The scrolling bar at the top of the customer site.
        'ticker_enabled' => '1',
        'ticker_new_services' => '1',
        'ticker_speed' => 'normal',
        // Customers must tick "Agree with terms of services" before ordering.
        'require_terms' => '1',
        'terms_text' => '',
        // Branding: an uploaded logo replaces the letter badge everywhere (path under public/).
        'site_logo' => '',
        // Wide logo (icon + name) for the headers: one for light pages, one for dark pages.
        'site_logo_wide' => '',
        'site_logo_wide_dark' => '',
        // Public home page for visitors (the "/" address).
        'contact_phone' => '',
        'contact_email' => '',
        'home_tagline' => 'Your premium direct source for mobile unlocking services',
        'home_welcome' => 'Welcome! Sign in to order services, or create your account now to get started.',
        'home_popup_enabled' => '1',
        'home_popup_badge' => 'VIP NOTICE BOARD',
        'home_popup_subtitle' => 'Your premium direct source for mobile security solutions',
        'home_popup_text' => '',
        'home_offer_enabled' => '0',
        'home_offer_title' => '',
        'home_offer_text' => '',
        'home_offer_ends_at' => '',
    ];

    private ?array $loaded = null;

    public function all(): array
    {
        if ($this->loaded === null) {
            try {
                $this->loaded = Setting::pluck('value', 'key')->all();
            } catch (Throwable) {
                // Table not migrated yet: fall back to defaults instead of breaking artisan.
                return self::DEFAULTS;
            }
        }

        return array_merge(self::DEFAULTS, $this->loaded);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->all()[$key] ?? null;

        return ($value === null || $value === '') ? ($default ?? (self::DEFAULTS[$key] ?? null)) : $value;
    }

    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        $this->loaded = null;
    }
}
