@extends('layouts.panel')

@section('title', __('Settings'))
@section('eyebrow', __('Platform'))
@section('page-title', __('Platform settings'))
@section('page-description', __('Branding, customer chat buttons, and how new customer accounts are handled. Credit amounts are shown in dollars.'))

@section('content')
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
                    <input type="datetime-local" id="home_offer_ends_at" name="home_offer_ends_at" value="{{ old('home_offer_ends_at', $settings['home_offer_ends_at']) }}" dir="ltr">
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
                        <option value="active" @selected(old('new_account_status', $settings['new_account_status']) === 'active')>{{ __('Active (can sign in immediately)') }}</option>
                        <option value="pending" @selected(old('new_account_status', $settings['new_account_status']) === 'pending')>{{ __('Pending (you approve each one)') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ __('Save settings') }}</button>
        </div>
    </form>
@endsection
