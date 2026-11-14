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
        ]);

        $this->saveLogo('site_logo', $data['site_logo'] ?? null, !empty($data['remove_logo']));
        $this->saveLogo('site_logo_wide', $data['site_logo_wide'] ?? null, !empty($data['remove_logo_wide']));
        $this->saveLogo('site_logo_wide_dark', $data['site_logo_wide_dark'] ?? null, !empty($data['remove_logo_wide_dark']));

        $this->logs->log('settings_saved', 'Updated platform settings', null, [], $adminId);
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
