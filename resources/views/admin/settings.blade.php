@extends('layouts.panel')

@section('title', __('Settings'))
@section('eyebrow', __('Platform'))
@section('page-title', __('Platform settings'))
@section('page-description', __('Branding, customer chat buttons, and how new customer accounts are handled. Credit amounts are shown in dollars.'))

@php $maintenance = $settings['maintenance_enabled'] === '1'; @endphp

@section('page-actions')
    {{-- One click on / off, without touching the rest of the settings. --}}
    <form method="post" action="{{ route('admin.maintenance') }}" class="inline" data-once
          @unless ($maintenance) data-confirm="{{ __('Turn maintenance mode on? Visitors and customers will only see the maintenance page until you turn it off.') }}" @endunless>
        @csrf
        <input type="hidden" name="enabled" value="{{ $maintenance ? '0' : '1' }}">
        <button type="submit" class="btn {{ $maintenance ? 'btn-success' : 'btn-danger' }}">
            @include('partials.icon', ['name' => 'tool']) {{ $maintenance ? __('Turn maintenance off') : __('Turn maintenance on') }}
        </button>
    </form>
@endsection

@section('content')
    @if ($maintenance)
        <div class="alert alert-error">
            <b>{{ __('Maintenance mode is on.') }}</b>
            {{ __('Visitors and customers only see the maintenance page. You see the site normally because you are signed in as admin.') }}
            @php $launchAt = \App\Support\MaintenanceSchedule::endsAt(); @endphp
            @if ($launchAt && $launchAt->isFuture())
                {{ __('It opens again by itself on :date.', ['date' => $launchAt->format('m/d/Y h:i A') . ' (GMT' . $launchAt->format('P') . ')']) }}
            @endif
        </div>
    @endif

    @include('partials.page-guide', [
        'key' => 'admin-platform-settings',
        'title' => 'How platform settings work',
        'intro' => 'Use these settings to control your branding and what visitors and customers see.',
        'steps' => [
            '<b>Platform name and logo</b> appear across the site. The uploaded logo is also used as the browser tab icon.',
            '<b>Support contact and chat buttons</b> give customers a way to reach you. Enable each chat button and enter its number or username.',
            '<b>Home page</b> settings control the visitor welcome text, notice-board popup, contact details and optional flash offer.',
            '<b>Announcement bar</b> settings show new services and manage customer notices. Use the Announcement bar page to add messages and set scroll speed.',
            '<b>Registration and orders</b> settings control whether customers can register, whether new accounts need approval, and whether terms must be accepted.',
        ],
        'note' => 'Select Save settings at the bottom to apply your changes.',
    ])

    <form method="post" action="{{ route('admin.settings.update') }}" class="stack" data-once enctype="multipart/form-data">
        @csrf @method('put')

        <div class="card" id="maintenance">
            <div class="card-head">
                <div>
                    <h2>{{ __('Maintenance mode') }}</h2>
                    <p>{{ __('While it is on, visitors and customers see only a maintenance page. Admins still see the site, and /admin/login stays open. Other servers using your API are not affected.') }}</p>
                </div>
                <span class="badge {{ $maintenance ? 'badge-failed' : 'badge-active' }}">{{ $maintenance ? __('On') : __('Off') }}</span>
            </div>
            <div class="card-body form-grid">
                <div class="full settings-sub">
                    <input type="hidden" name="maintenance_enabled" value="0">
                    <label class="check"><input type="checkbox" name="maintenance_enabled" value="1" @checked(old('maintenance_enabled', $settings['maintenance_enabled']) == '1')> <span><b>{{ __('Site under maintenance') }}</b><br><span class="muted">{{ __('Visitors and customers see the maintenance page instead of the site.') }}</span></span></label>
                </div>
                <div class="full">
                    @php $launch = \App\Support\MaintenanceSchedule::endsAt(); @endphp
                    <label for="maintenance_ends_at">{{ __('Back online at') }} <span class="muted">({{ __('optional') }})</span></label>
                    <input type="datetime-local" id="maintenance_ends_at" name="maintenance_ends_at" value="{{ old('maintenance_ends_at', $settings['maintenance_ends_at']) }}" dir="ltr" style="max-width: 280px"
                           data-local-time="maintenance_ends_tz" @if (!old('maintenance_ends_at') && $launch) data-instant="{{ $launch->toIso8601String() }}" @endif>
                    {{-- Your browser's time zone, filled in below, so the time means your local time. --}}
                    <input type="hidden" name="maintenance_ends_tz" id="maintenance_ends_tz" value="{{ old('maintenance_ends_tz', $settings['maintenance_ends_tz']) }}">
                    <p class="help">
                        {{ __('The maintenance page shows this date with a countdown, and the site opens again by itself at this time. Leave empty to keep maintenance on until you turn it off.') }}
                        <span data-tz-note>{{ __('Time zone: :tz', ['tz' => \App\Support\MaintenanceSchedule::zone($settings['maintenance_ends_tz'])]) }}</span>
                        @if ($launch && $launch->isFuture() && $maintenance)
                            <br><b>{{ __('Opens again in :time.', ['time' => now()->diffForHumans($launch, ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2])]) }}</b>
                        @endif
                    </p>
                    @error('maintenance_ends_at') <p class="error-text">{{ $message }}</p> @enderror
                    <script>
                        // Date fields marked data-local-time="<hidden tz field>": the time typed is this browser's local
                        // time, so its time zone is sent along; a saved time is shown converted to local time.
                        document.addEventListener('DOMContentLoaded', function () {
                            try {
                                var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                                if (!tz) { return; }
                                var p = function (n) { return (n < 10 ? '0' : '') + n; };
                                document.querySelectorAll('input[data-local-time]').forEach(function (input) {
                                    var field = document.getElementById(input.getAttribute('data-local-time'));
                                    if (!field) { return; }
                                    field.value = tz;
                                    var at = input.getAttribute('data-instant') ? new Date(input.getAttribute('data-instant')) : null;
                                    if (at && !isNaN(at.getTime())) {
                                        input.value = at.getFullYear() + '-' + p(at.getMonth() + 1) + '-' + p(at.getDate()) + 'T' + p(at.getHours()) + ':' + p(at.getMinutes());
                                    }
                                });
                                var note = document.querySelector('[data-tz-note]');
                                if (note) { note.textContent = @json(__('Time zone: :tz', ['tz' => '__TZ__'])).replace('__TZ__', tz); }
                            } catch (e) { /* old browser: the saved or server time zone is used */ }
                        });
                    </script>
                </div>
                <div>
                    <label for="maintenance_badge">{{ __('Badge (English)') }}</label>
                    <input type="text" id="maintenance_badge" name="maintenance_badge" value="{{ old('maintenance_badge', $settings['maintenance_badge']) }}" maxlength="80">
                </div>
                <div>
                    <label for="maintenance_badge_ar">{{ __('Badge (Arabic)') }}</label>
                    <input type="text" id="maintenance_badge_ar" name="maintenance_badge_ar" value="{{ old('maintenance_badge_ar', $settings['maintenance_badge_ar']) }}" maxlength="80" dir="rtl">
                </div>
                <div>
                    <label for="maintenance_headline">{{ __('Headline (English)') }} <span class="muted">({{ __('optional') }})</span></label>
                    <input type="text" id="maintenance_headline" name="maintenance_headline" value="{{ old('maintenance_headline', $settings['maintenance_headline']) }}" maxlength="120" placeholder="🚀 The Grand Opening Is Almost Here!">
                </div>
                <div>
                    <label for="maintenance_headline_ar">{{ __('Headline (Arabic)') }} <span class="muted">({{ __('optional') }})</span></label>
                    <input type="text" id="maintenance_headline_ar" name="maintenance_headline_ar" value="{{ old('maintenance_headline_ar', $settings['maintenance_headline_ar']) }}" maxlength="120" dir="rtl" placeholder="🚀 الافتتاح الكبير اقترب!">
                </div>
                <div class="full">
                    <p class="help" style="margin: 0">{{ __('With a "Back online at" time the page becomes an opening page: rocket, big headline, opening message, perks and the countdown. Empty headline and message fields use the opening texts shown as examples.') }}</p>
                </div>
                <div>
                    <label for="maintenance_text">{{ __('Message (English)') }} <span class="muted">({{ __('optional') }})</span></label>
                    <textarea id="maintenance_text" name="maintenance_text" maxlength="2000" rows="5" placeholder="{{ __('Empty shows a standard "we will be back soon" message.') }}">{{ old('maintenance_text', $settings['maintenance_text']) }}</textarea>
                </div>
                <div>
                    <label for="maintenance_text_ar">{{ __('Message (Arabic)') }} <span class="muted">({{ __('optional') }})</span></label>
                    <textarea id="maintenance_text_ar" name="maintenance_text_ar" maxlength="2000" rows="5" dir="rtl" placeholder="{{ __('Empty shows a standard "we will be back soon" message.') }}">{{ old('maintenance_text_ar', $settings['maintenance_text_ar']) }}</textarea>
                </div>
                <div>
                    <label for="maintenance_email">{{ __('Help email') }} <span class="muted">({{ __('optional') }})</span></label>
                    <input type="email" id="maintenance_email" name="maintenance_email" value="{{ old('maintenance_email', $settings['maintenance_email']) }}" maxlength="150" dir="ltr" placeholder="{{ $settings['contact_email'] ?: 'info@example.com' }}">
                    <p class="help">{{ __('Shown as "Need help? Email us at…". Empty uses the contact email below.') }}</p>
                    @error('maintenance_email') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label>&nbsp;</label>
                    <input type="hidden" name="maintenance_show_visits" value="0">
                    <label class="check"><input type="checkbox" name="maintenance_show_visits" value="1" @checked(old('maintenance_show_visits', $settings['maintenance_show_visits']) == '1')> <span>{{ __('Show the visit counter on the page') }}</span></label>
                </div>
                <div class="full">
                    <p class="help" style="margin: 0">{{ __('Line breaks in the messages are kept. The page follows the visitor\'s language and has its own English / العربية switch.') }}</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>{{ __('General') }}</h2></div>
            <div class="card-body form-grid">
                <div>
                    <label for="site_name">{{ __('Platform name') }}</label>
                    <input type="text" id="site_name" name="site_name" value="{{ old('site_name', $settings['site_name']) }}" required maxlength="60">
                    <p class="help">{{ __('Shown in the sidebar, page titles and sign-in pages.') }}</p>
                </div>
                <div>
                    <label for="site_logo">{{ __('Logo') }} <span class="muted">({{ __('optional') }})</span></label>
                    <div class="logo-row">
                        @include('partials.brand-mark')
                        <input type="file" id="site_logo" name="site_logo" accept="image/png,image/jpeg,image/webp">
                    </div>
                    <p class="help">{{ __('PNG, JPG or WebP, square works best, up to 1 MB. Replaces the letter badge everywhere.') }}</p>
                    @if ($settings['site_logo'])
                        <label class="check" style="margin-top: 6px"><input type="checkbox" name="remove_logo" value="1"> <span>{{ __('Remove the logo') }}</span></label>
                    @endif
                    @error('site_logo') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                @foreach (['site_logo_wide' => ['Wide logo for light pages', 'remove_logo_wide', '#ffffff'], 'site_logo_wide_dark' => ['Wide logo for dark pages', 'remove_logo_wide_dark', '#0a1424']] as $field => [$label, $remove, $bg])
                    <div>
                        <label for="{{ $field }}">{{ __($label) }} <span class="muted">({{ __('optional') }})</span></label>
                        @if ($settings[$field])
                            <div class="logo-preview" style="background: {{ $bg }}"><img src="{{ asset($settings[$field]) }}" alt=""></div>
                        @endif
                        <input type="file" id="{{ $field }}" name="{{ $field }}" accept="image/png,image/jpeg,image/webp">
                        <p class="help">{{ __('Icon and name in one image, shown in the page headers. Transparent PNG, up to 2 MB.') }}</p>
                        @if ($settings[$field])
                            <label class="check" style="margin-top: 6px"><input type="checkbox" name="{{ $remove }}" value="1"> <span>{{ __('Remove') }}</span></label>
                        @endif
                        @error($field) <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                @endforeach
                <div class="full">
                    <label for="support_contact">{{ __('Support contact') }} <span class="muted">({{ __('optional') }})</span></label>
                    <input type="text" id="support_contact" name="support_contact" value="{{ old('support_contact', $settings['support_contact']) }}" maxlength="150" placeholder="{{ __('WhatsApp number or email customers can reach you on') }}">
                </div>
                <div class="full">
                    <label for="announcement">{{ __('Fixed notice') }} <span class="muted">({{ __('optional') }})</span></label>
                    <textarea id="announcement" name="announcement" maxlength="500" placeholder="{{ __('Shown at the top of every customer page. Leave empty for none.') }}">{{ old('announcement', $settings['announcement']) }}</textarea>
                    <p class="help">{{ __('For the scrolling bar, use') }} <a href="{{ route('admin.announcements.index') }}">{{ __('Announcement bar') }}</a>.</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <h2>{{ __('WhatsApp and Telegram chat buttons') }}</h2>
                    <p>{{ __('Round buttons in the corner of the home page, sign-in pages and every customer page. Tick a box and fill in its number or username to show it; untick to hide it.') }}</p>
                </div>
            </div>
            <div class="card-body form-grid">
                <div>
                    <input type="hidden" name="whatsapp_enabled" value="0">
                    <label class="check" style="margin-bottom: 10px">
                        <input type="checkbox" name="whatsapp_enabled" value="1" @checked(old('whatsapp_enabled', $settings['whatsapp_enabled']) == '1')>
                        <span>{{ __('Show the WhatsApp button') }}</span>
                    </label>
                    <label for="whatsapp_number">{{ __('WhatsApp number') }}</label>
                    <input type="tel" id="whatsapp_number" name="whatsapp_number" value="{{ old('whatsapp_number', $settings['whatsapp_number']) }}" maxlength="25" placeholder="9665XXXXXXXX" dir="ltr">
                    <p class="help">{{ __('With the country code, without + or spaces.') }}</p>
                    @error('whatsapp_number') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <input type="hidden" name="telegram_enabled" value="0">
                    <label class="check" style="margin-bottom: 10px">
                        <input type="checkbox" name="telegram_enabled" value="1" @checked(old('telegram_enabled', $settings['telegram_enabled']) == '1')>
                        <span>{{ __('Show the Telegram button') }}</span>
                    </label>
                    <label for="telegram_username">{{ __('Telegram username') }}</label>
                    <input type="text" id="telegram_username" name="telegram_username" value="{{ old('telegram_username', $settings['telegram_username']) }}" maxlength="40" placeholder="your_username" dir="ltr">
                    <p class="help">{{ __('The name after t.me/ (a user, group or channel).') }}</p>
                    @error('telegram_username') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="card" id="home-page">
            <div class="card-head">
                <div>
                    <h2>{{ __('Home page (visitors)') }}</h2>
                    <p>{{ __('What people see at the main address before they sign in.') }} <a href="{{ route('home') }}" target="_blank" rel="noopener">{{ __('Open the home page') }}</a></p>
                </div>
            </div>
            <div class="card-body form-grid">
                <div>
                    <label for="contact_phone">{{ __('Contact phone') }} <span class="muted">({{ __('optional') }})</span></label>
                    <input type="tel" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $settings['contact_phone']) }}" maxlength="30" placeholder="+249 112 758 148" dir="ltr">
                    @error('contact_phone') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="contact_email">{{ __('Contact email') }} <span class="muted">({{ __('optional') }})</span></label>
                    <input type="email" id="contact_email" name="contact_email" value="{{ old('contact_email', $settings['contact_email']) }}" maxlength="150" placeholder="support@example.com" dir="ltr">
                    @error('contact_email') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="full">
                    <label for="home_tagline">{{ __('Tagline') }}</label>
                    <input type="text" id="home_tagline" name="home_tagline" value="{{ old('home_tagline', $settings['home_tagline']) }}" maxlength="150">
                    <p class="help">{{ __('One line under your server name, also used by search engines.') }}</p>
                </div>
                <div class="full">
                    <label for="home_welcome">{{ __('Welcome text') }}</label>
                    <textarea id="home_welcome" name="home_welcome" maxlength="500" rows="2">{{ old('home_welcome', $settings['home_welcome']) }}</textarea>
                    <p class="help">{{ __('The big yellow announcement on the home page uses the "Fixed notice" above.') }}</p>
                </div>

                <div class="full settings-sub">
                    <input type="hidden" name="home_popup_enabled" value="0">
                    <label class="check"><input type="checkbox" name="home_popup_enabled" value="1" @checked(old('home_popup_enabled', $settings['home_popup_enabled']) == '1')> <span><b>{{ __('Show the notice-board popup') }}</b><br><span class="muted">{{ __('Opens once per visit; visitors close it with the Close button.') }}</span></span></label>
                </div>
                <div>
                    <label for="home_popup_badge">{{ __('Popup badge') }}</label>
                    <input type="text" id="home_popup_badge" name="home_popup_badge" value="{{ old('home_popup_badge', $settings['home_popup_badge']) }}" maxlength="40">
                </div>
                <div>
                    <label for="home_popup_subtitle">{{ __('Popup subtitle') }}</label>
                    <input type="text" id="home_popup_subtitle" name="home_popup_subtitle" value="{{ old('home_popup_subtitle', $settings['home_popup_subtitle']) }}" maxlength="150">
                </div>
                <div class="full">
                    <label for="home_popup_text">{{ __('Popup message') }} <span class="muted">({{ __('optional') }})</span></label>
                    <textarea id="home_popup_text" name="home_popup_text" maxlength="2000" rows="4" dir="auto" placeholder="{{ __('Arabic or English. Line breaks are kept. Empty shows the welcome text.') }}">{{ old('home_popup_text', $settings['home_popup_text']) }}</textarea>
                    <p class="help">{{ __('The three boxes under the message are your first three categories.') }}</p>
                </div>

                <div class="full settings-sub">
                    <input type="hidden" name="home_offer_enabled" value="0">
                    <label class="check"><input type="checkbox" name="home_offer_enabled" value="1" @checked(old('home_offer_enabled', $settings['home_offer_enabled']) == '1')> <span><b>{{ __('Show a flash offer') }}</b><br><span class="muted">{{ __('A highlighted box at the top with a countdown. It hides itself when the time is up.') }}</span></span></label>
                </div>
                <div>
                    <label for="home_offer_title">{{ __('Offer title') }}</label>
                    <input type="text" id="home_offer_title" name="home_offer_title" value="{{ old('home_offer_title', $settings['home_offer_title']) }}" maxlength="120" placeholder="{{ __('Massive discounts on all server services!') }}">
                    @error('home_offer_title') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="home_offer_ends_at">{{ __('Offer ends at') }} <span class="muted">({{ __('optional') }})</span></label>
                    @php $offerAt = \App\Support\MaintenanceSchedule::parse($settings['home_offer_ends_at'], $settings['home_offer_ends_tz'] ?? null); @endphp
                    <input type="datetime-local" id="home_offer_ends_at" name="home_offer_ends_at" value="{{ old('home_offer_ends_at', $settings['home_offer_ends_at']) }}" dir="ltr"
                           data-local-time="home_offer_ends_tz" @if (!old('home_offer_ends_at') && $offerAt) data-instant="{{ $offerAt->toIso8601String() }}" @endif>
                    <input type="hidden" name="home_offer_ends_tz" id="home_offer_ends_tz" value="{{ old('home_offer_ends_tz', $settings['home_offer_ends_tz'] ?? '') }}">
                    <p class="help">{{ __('Your local time. The box hides itself when the time is up.') }}</p>
                    @error('home_offer_ends_at') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="full">
                    <label for="home_offer_text">{{ __('Offer details') }} <span class="muted">({{ __('optional') }})</span></label>
                    <input type="text" id="home_offer_text" name="home_offer_text" value="{{ old('home_offer_text', $settings['home_offer_text']) }}" maxlength="200">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>{{ __('Services and orders') }}</h2></div>
            <div class="card-body form-grid">
                <div>
                    <label for="new_badge_days">{{ __('"New" tag lasts (days)') }}</label>
                    <input type="number" id="new_badge_days" name="new_badge_days" value="{{ old('new_badge_days', $settings['new_badge_days']) }}" min="0" max="365" required dir="ltr">
                    <p class="help">{{ __('A service shows the "New" tag for this many days after it is created. 0 turns the tag off.') }}</p>
                    @error('new_badge_days') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <span class="label">{{ __('Announcement bar') }}</span>
                    <input type="hidden" name="ticker_enabled" value="0">
                    <label class="check" style="margin-bottom: 8px">
                        <input type="checkbox" name="ticker_enabled" value="1" @checked(old('ticker_enabled', $settings['ticker_enabled']) == '1')>
                        <span>{{ __('Show the announcement bar to customers') }}</span>
                    </label>
                    <input type="hidden" name="ticker_new_services" value="0">
                    <label class="check">
                        <input type="checkbox" name="ticker_new_services" value="1" @checked(old('ticker_new_services', $settings['ticker_new_services']) == '1')>
                        <span>{{ __('List new services in the bar automatically') }}</span>
                    </label>
                </div>
                <div class="full">
                    <input type="hidden" name="require_terms" value="0">
                    <label class="check">
                        <input type="checkbox" name="require_terms" value="1" @checked(old('require_terms', $settings['require_terms']) == '1')>
                        <span>{{ __('Customers must tick "Agree with terms of services" before ordering') }}</span>
                    </label>
                </div>
                <div class="full">
                    <input type="hidden" name="show_terms_link" value="0">
                    <label class="check" style="margin-bottom: 8px">
                        <input type="checkbox" name="show_terms_link" value="1" @checked(old('show_terms_link', $settings['show_terms_link']) == '1')>
                        <span>{{ __('Show "Terms of Service" in the customer menu and page footer') }}</span>
                    </label>
                    <input type="hidden" name="show_privacy_link" value="0">
                    <label class="check">
                        <input type="checkbox" name="show_privacy_link" value="1" @checked(old('show_privacy_link', $settings['show_privacy_link']) == '1')>
                        <span>{{ __('Show "Privacy Policy" in the customer menu and page footer') }}</span>
                    </label>
                </div>
                <div class="full">
                    <label for="terms_text">{{ __('Terms of services') }} <span class="muted">({{ __('optional') }})</span></label>
                    <textarea id="terms_text" name="terms_text" maxlength="5000" rows="5" placeholder="{{ __('Your own extra rules, e.g. about a specific service.') }}">{{ old('terms_text', $settings['terms_text']) }}</textarea>
                    <p class="help">{{ __('The full Terms of Service and Privacy Policy pages are already written (Arabic and English). Text here is added at the end of the Terms page as "Additional terms".') }}
                        <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">{{ __('Terms of Service') }}</a> · <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">{{ __('Privacy Policy') }}</a></p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>{{ __('Registration') }}</h2></div>
            <div class="card-body form-grid">
                <div class="full">
                    <input type="hidden" name="allow_registration" value="0">
                    <label class="check">
                        <input type="checkbox" name="allow_registration" value="1" @checked(old('allow_registration', $settings['allow_registration']) == '1')>
                        <span>{{ __('Allow new customers to register themselves') }}<br><span class="muted">{{ __('When off, only you can create customer accounts.') }}</span></span>
                    </label>
                </div>
                <div>
                    <label for="new_account_status">{{ __('New accounts start as') }}</label>
                    <select id="new_account_status" name="new_account_status" required>
                        <option value="active" @selected(old('new_account_status', $settings['new_account_status']) === 'active')>{{ __('Active once the email is confirmed (otherwise you approve)') }}</option>
                        <option value="pending" @selected(old('new_account_status', $settings['new_account_status']) === 'pending')>{{ __('Pending (you approve each one)') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ __('Save settings') }}</button>
        </div>
    </form>

    <form method="post" action="{{ route('admin.settings.import.upload') }}" class="stack" id="import-services" data-once enctype="multipart/form-data" style="margin-top: 24px">
        @csrf
        <div class="card">
            <div class="card-head">
                <div>
                    <h2>{{ __('Import services from a text file') }}</h2>
                    <p>{{ __('Upload a supplier price list saved as .txt. You will see every service found before anything is imported.') }}</p>
                </div>
            </div>
            <div class="card-body form-grid">
                <div>
                    <label for="import_file">{{ __('Text file') }} (.txt)</label>
                    <input type="file" id="import_file" name="import_file" accept=".txt,text/plain" required>
                    <p class="help">{{ __('Up to 1 MB and 500 services.') }}</p>
                    @error('import_file') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <span class="label">{{ __('File layout') }}</span>
<pre class="mono" dir="ltr" style="margin: 0; white-space: pre-wrap; font-size: .85rem">IMEI Services
Honor Frp Key by Sn Only Instant
Honor Frp - Direct Source Services
$42.5 Instant</pre>
                    <p class="help">{{ __('First line: the category every service goes into. Then three lines per service: name, supplier line (ignored), price and delivery time.') }}</p>
                </div>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ __('Read the file') }}</button>
        </div>
    </form>

    <form method="post" action="{{ route('admin.settings.catalog.import') }}" class="stack" id="catalog-transfer" data-once enctype="multipart/form-data" style="margin-top: 24px"
          data-confirm="{{ __('Import this catalog into this site? Missing categories and services are added; nothing is deleted.') }}">
        @csrf
        <div class="card">
            <div class="card-head">
                <div>
                    <h2>{{ __('Export / Import all categories and services') }}</h2>
                    <p>{{ __('Copy the whole catalog to another site, e.g. from DEV to PROD: export it here, then import the file on the other site.') }}</p>
                </div>
            </div>
            <div class="card-body form-grid">
                <div>
                    <span class="label">{{ __('Export') }}</span>
                    <p><a href="{{ route('admin.settings.catalog.export') }}" class="btn">{{ __('Download export file') }} (.json)</a></p>
                    <p class="help">{{ __('Every category with its input fields, and every service with its prices, VIP price, delivery time and API connection. Archived items are left out.') }}</p>
                </div>
                <div>
                    <label for="catalog_file">{{ __('Import an export file') }} (.json)</label>
                    <input type="file" id="catalog_file" name="catalog_file" accept=".json,application/json" required>
                    @error('catalog_file') <p class="error-text">{{ $message }}</p> @enderror
                    <label class="check" style="margin-top: 10px">
                        <input type="checkbox" name="update_existing" value="1" checked>
                        <span>{{ __('Update categories and services that already exist') }}<br><span class="muted">{{ __('Matched by name. When off, they are left as they are and only missing ones are added.') }}</span></span>
                    </label>
                    <p class="help">{{ __('API connections are matched to this site\'s providers by URL or name; add the providers here first.') }}</p>
                </div>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ __('Import catalog') }}</button>
        </div>
    </form>
@endsection
