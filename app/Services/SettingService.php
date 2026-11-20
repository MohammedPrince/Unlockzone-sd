<?php

namespace App\Services;

use App\Repositories\ActivityLogRepository;
use App\Repositories\SettingRepository;

class SettingService
{
    public function __construct(
        private SettingRepository $settings,
        private ActivityLogRepository $logs,
    ) {
    }

    public function all(): array
    {
        return $this->settings->all();
    }

    public function save(array $data, int $adminId): void
    {
        $this->settings->setMany([
            'site_name' => $data['site_name'],
            'allow_registration' => !empty($data['allow_registration']) ? '1' : '0',
            'new_account_status' => $data['new_account_status'],
            'support_contact' => $data['support_contact'] ?? '',
            'announcement' => $data['announcement'] ?? '',
            'new_badge_days' => (string) (int) ($data['new_badge_days'] ?? 0),
            'whatsapp_enabled' => !empty($data['whatsapp_enabled']) ? '1' : '0',
            // Stored as digits only, the way wa.me links need it.
            'whatsapp_number' => preg_replace('/[^0-9]+/', '', (string) ($data['whatsapp_number'] ?? '')),
            'telegram_enabled' => !empty($data['telegram_enabled']) ? '1' : '0',
            'telegram_username' => ltrim(trim((string) ($data['telegram_username'] ?? '')), '@'),
            'ticker_enabled' => !empty($data['ticker_enabled']) ? '1' : '0',
            'ticker_new_services' => !empty($data['ticker_new_services']) ? '1' : '0',
            'require_terms' => !empty($data['require_terms']) ? '1' : '0',
            'show_terms_link' => !empty($data['show_terms_link']) ? '1' : '0',
            'show_privacy_link' => !empty($data['show_privacy_link']) ? '1' : '0',
            'terms_text' => $data['terms_text'] ?? '',
            'contact_phone' => trim((string) ($data['contact_phone'] ?? '')),
            'contact_email' => trim((string) ($data['contact_email'] ?? '')),
            'home_tagline' => $data['home_tagline'] ?? '',
            'home_welcome' => $data['home_welcome'] ?? '',
            'home_popup_enabled' => !empty($data['home_popup_enabled']) ? '1' : '0',
            'home_popup_badge' => $data['home_popup_badge'] ?? '',
            'home_popup_subtitle' => $data['home_popup_subtitle'] ?? '',
            'home_popup_text' => $data['home_popup_text'] ?? '',
            'home_offer_enabled' => !empty($data['home_offer_enabled']) ? '1' : '0',
            'home_offer_title' => $data['home_offer_title'] ?? '',
            'home_offer_text' => $data['home_offer_text'] ?? '',
            'home_offer_ends_at' => $data['home_offer_ends_at'] ?? '',
            // The admin's browser time zone: the offer's end time is their local time.
            'home_offer_ends_tz' => \App\Support\MaintenanceSchedule::zone($data['home_offer_ends_tz'] ?? null),
            'maintenance_badge' => trim((string) ($data['maintenance_badge'] ?? '')),
            'maintenance_badge_ar' => trim((string) ($data['maintenance_badge_ar'] ?? '')),
            'maintenance_headline' => trim((string) ($data['maintenance_headline'] ?? '')),
            'maintenance_headline_ar' => trim((string) ($data['maintenance_headline_ar'] ?? '')),
            'maintenance_text' => $data['maintenance_text'] ?? '',
            'maintenance_text_ar' => $data['maintenance_text_ar'] ?? '',
            'maintenance_email' => trim((string) ($data['maintenance_email'] ?? '')),
            'maintenance_show_visits' => !empty($data['maintenance_show_visits']) ? '1' : '0',
            'maintenance_ends_at' => trim((string) ($data['maintenance_ends_at'] ?? '')),
            'maintenance_ends_tz' => \App\Support\MaintenanceSchedule::zone($data['maintenance_ends_tz'] ?? null),
        ]);

        $this->setMaintenance(!empty($data['maintenance_enabled']), $adminId);

        $this->saveLogo('site_logo', $data['site_logo'] ?? null, !empty($data['remove_logo']));
        $this->saveLogo('site_logo_wide', $data['site_logo_wide'] ?? null, !empty($data['remove_logo_wide']));
        $this->saveLogo('site_logo_wide_dark', $data['site_logo_wide_dark'] ?? null, !empty($data['remove_logo_wide_dark']));

        $this->logs->log('settings_saved', 'Updated platform settings', null, [], $adminId);
    }

    public function maintenanceOn(): bool
    {
        return $this->settings->get('maintenance_enabled') === '1';
    }

    /** Turns maintenance mode on or off; only a real change is written to the activity log. */
    public function setMaintenance(bool $on, int $adminId): void
    {
        if ($on === $this->maintenanceOn()) {
            return;
        }

        $values = ['maintenance_enabled' => $on ? '1' : '0'];
        // A launch time that has already passed would reopen the site at once: turning maintenance on drops it.
        if ($on && \App\Support\MaintenanceSchedule::endsAt()?->isPast()) {
            $values['maintenance_ends_at'] = '';
        }

        $this->settings->setMany($values);
        $this->logs->log($on ? 'maintenance_on' : 'maintenance_off', $on ? 'Turned maintenance mode on' : 'Turned maintenance mode off', null, [], $adminId);
    }

    /**
     * The launch time has passed: maintenance turns itself off and the site is open again.
     * Returns true when it did (called on every request while maintenance is on).
     */
    public function endMaintenanceIfDue(): bool
    {
        $endsAt = \App\Support\MaintenanceSchedule::endsAt();

        if (!$this->maintenanceOn() || !$endsAt || $endsAt->isFuture()) {
            return false;
        }

        $this->settings->setMany(['maintenance_enabled' => '0', 'maintenance_ends_at' => '']);
        $this->logs->log('maintenance_off', 'Maintenance mode ended by itself at the launch time ' . $endsAt->format('Y-m-d H:i T'));

        return true;
    }

    /** Stores a new logo image under public/uploads/branding (or removes it) and deletes the old file. */
    private function saveLogo(string $key, ?\Illuminate\Http\UploadedFile $file, bool $remove): void
    {
        $old = (string) $this->settings->get($key, '');

        if (!$file && !$remove) {
            return;
        }

        $path = '';
        if ($file) {
            $name = str_replace('_', '-', $key) . '-' . \Illuminate\Support\Str::random(12) . '.' . $file->extension();
            $file->move(public_path('uploads/branding'), $name);
            $path = 'uploads/branding/' . $name;
        }

        $this->settings->setMany([$key => $path]);

        // Only ever delete a file we created ourselves.
        if ($old !== '' && str_starts_with($old, 'uploads/branding/') && is_file(public_path($old))) {
            @unlink(public_path($old));
        }
    }
}
