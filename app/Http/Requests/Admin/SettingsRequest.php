<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:60'],
            'allow_registration' => ['nullable', 'boolean'],
            'new_account_status' => ['required', Rule::in(['active', 'pending'])],
            'support_contact' => ['nullable', 'string', 'max:150'],
            'announcement' => ['nullable', 'string', 'max:500'],
            'new_badge_days' => ['required', 'integer', 'min:0', 'max:365'],
            'whatsapp_enabled' => ['nullable', 'boolean'],
            'whatsapp_number' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+ ()\-]+$/', 'required_if:whatsapp_enabled,1'],
            'telegram_enabled' => ['nullable', 'boolean'],
            'telegram_username' => ['nullable', 'string', 'max:40', 'regex:/^@?[A-Za-z0-9_]{3,}$/', 'required_if:telegram_enabled,1'],
            'ticker_enabled' => ['nullable', 'boolean'],
            'ticker_new_services' => ['nullable', 'boolean'],
            'require_terms' => ['nullable', 'boolean'],
            'show_terms_link' => ['nullable', 'boolean'],
            'show_privacy_link' => ['nullable', 'boolean'],
            'terms_text' => ['nullable', 'string', 'max:5000'],
            // SVG is left out on purpose: it can carry scripts.
            'site_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
            'site_logo_wide' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'site_logo_wide_dark' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo_wide' => ['nullable', 'boolean'],
            'remove_logo_wide_dark' => ['nullable', 'boolean'],
            'contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+ ()\-]+$/'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'home_tagline' => ['nullable', 'string', 'max:150'],
            'google_site_verification' => ['nullable', 'string', 'max:300'],
            'public_catalog' => ['nullable', 'boolean'],
            'public_prices' => ['nullable', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_title_ar' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:170'],
            'seo_description_ar' => ['nullable', 'string', 'max:170'],
            'home_welcome' => ['nullable', 'string', 'max:500'],
            'home_popup_enabled' => ['nullable', 'boolean'],
            'home_popup_badge' => ['nullable', 'string', 'max:40'],
            'home_popup_subtitle' => ['nullable', 'string', 'max:150'],
            'home_popup_text' => ['nullable', 'string', 'max:2000'],
            'home_offer_enabled' => ['nullable', 'boolean'],
            'home_offer_title' => ['nullable', 'string', 'max:120', 'required_if:home_offer_enabled,1'],
            'home_offer_text' => ['nullable', 'string', 'max:200'],
            'home_offer_ends_at' => ['nullable', 'date'],
            'home_offer_ends_tz' => ['nullable', 'string', 'max:64'],
            'maintenance_enabled' => ['nullable', 'boolean'],
            'maintenance_badge' => ['nullable', 'string', 'max:80'],
            'maintenance_badge_ar' => ['nullable', 'string', 'max:80'],
            'maintenance_headline' => ['nullable', 'string', 'max:120'],
            'maintenance_headline_ar' => ['nullable', 'string', 'max:120'],
            'maintenance_text' => ['nullable', 'string', 'max:2000'],
            'maintenance_text_ar' => ['nullable', 'string', 'max:2000'],
            'maintenance_email' => ['nullable', 'email', 'max:150'],
            'maintenance_show_visits' => ['nullable', 'boolean'],
            // The local date/time the admin typed, read in their browser's time zone (filled in by the page).
            'maintenance_ends_at' => ['nullable', 'date', function ($attribute, $value, $fail) {
                $at = \App\Support\MaintenanceSchedule::parse($value, $this->input('maintenance_ends_tz'));
                if ($at && $at->isPast() && $this->boolean('maintenance_enabled')) {
                    $fail(__('The launch time must be in the future.'));
                }
            }],
            'maintenance_ends_tz' => ['nullable', 'string', 'max:64'],
        ];
    }
}
