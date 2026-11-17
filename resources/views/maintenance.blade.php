{{--
    Maintenance page shown to visitors and customers while Admin > Settings > Maintenance mode is on.
    Self-contained (own styles) so it works even if the site's assets are being updated.
    Text follows the visitor's language (English / Arabic switch at the bottom).
--}}
@php
    $ar = app()->getLocale() === 'ar';
    $site = (string) setting('site_name');
    $logoWide = (string) setting('site_logo_wide');
    $logo = (string) setting('site_logo');

    $badge = trim((string) setting($ar ? 'maintenance_badge_ar' : 'maintenance_badge'));
    $text = trim((string) setting($ar ? 'maintenance_text_ar' : 'maintenance_text'));
    if ($text === '') {
        $text = $ar
            ? "نعمل الآن على تحديث {$site} لنخدمك بشكل أفضل.\n\nسنعود قريبًا جدًا. شكرًا لصبرك."
            : "We are updating {$site} to serve you better.\n\nWe will be back very soon. Thank you for your patience.";
    }
    $email = trim((string) (setting('maintenance_email') ?: setting('contact_email')));
    $visits = setting('maintenance_show_visits') === '1'
        ? (app(\App\Repositories\VisitRepository::class)->footerCounts()['total'] ?? null)
        : null;
@endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    @include('partials.favicon')
    <title>{{ $ar ? 'الموقع تحت الصيانة' : 'Under maintenance' }} · {{ $site }}</title>
    <style>
        :root { --navy: #0b2545; --orange: #f26b1d; --text: #4b5563; --muted: #8a94a6; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 16px;
            font: 16px/1.7 "Segoe UI", system-ui, -apple-system, Roboto, Tahoma, Arial, sans-serif; color: var(--text);
            background: radial-gradient(circle at 92% 8%, #ffe9c7 0, transparent 42%), radial-gradient(circle at 0% 100%, #fff4e4 0, transparent 40%), #fbfaf8;
        }
        .card {
            width: 100%; max-width: 700px; background: #fff; border-radius: 32px; padding: 56px 44px 34px; text-align: center;
            box-shadow: 0 30px 80px -30px rgba(11, 37, 69, .25), 0 2px 6px rgba(11, 37, 69, .04);
        }
        .logo img { max-height: 64px; max-width: 220px; }
        .logo .name { font-size: 22px; font-weight: 800; color: var(--navy); }
        .icon {
            width: 80px; height: 80px; margin: 28px auto 24px; border-radius: 50%; background: #fdf0e1;
            display: flex; align-items: center; justify-content: center; font-size: 40px;
        }
        .icon svg { width: 44px; height: 44px; }
        .badge {
            display: inline-block; padding: 7px 16px; border-radius: 999px; background: #fdebdc; color: var(--orange);
            font-weight: 700; font-size: 14px;
        }
        .text { margin: 26px 0 0; white-space: pre-line; font-size: 17px; }
        .help { margin-top: 30px; font-size: 15px; }
        .help a { color: var(--orange); font-weight: 700; }
        .langs { display: inline-flex; gap: 4px; margin-top: 22px; padding: 5px; border: 1px solid #e7e9ee; border-radius: 14px; }
        .langs a { padding: 8px 18px; border-radius: 10px; color: #5b6475; font-weight: 700; text-decoration: none; font-size: 15px; }
        .langs a.on { background: var(--navy); color: #fff; }
        .visits { margin-top: 26px; font-size: 22px; color: var(--navy); }
        .foot { margin-top: 26px; font-size: 13px; color: var(--muted); }
        @media (max-width: 520px) { .card { padding: 40px 20px 26px; border-radius: 24px; } .text { font-size: 16px; } }
    </style>
</head>
<body>
    <main class="card">
        <div class="logo">
            @if ($logoWide !== '')
                <img src="{{ asset($logoWide) }}" alt="{{ $site }}">
            @elseif ($logo !== '')
                <img src="{{ asset($logo) }}" alt="{{ $site }}">
            @else
                <span class="name">{{ $site }}</span>
            @endif
        </div>

        <div class="icon" aria-hidden="true">
            {{-- A gear: "work in progress". --}}
            <svg viewBox="0 0 24 24" fill="#f5b400" stroke="#0b2545" stroke-width="1.4" stroke-linejoin="round"><path d="M10.3 2.5h3.4l.5 2.6 1.6.7 2.2-1.5 2.4 2.4-1.5 2.2.7 1.6 2.6.5v3.4l-2.6.5-.7 1.6 1.5 2.2-2.4 2.4-2.2-1.5-1.6.7-.5 2.6h-3.4l-.5-2.6-1.6-.7-2.2 1.5-2.4-2.4 1.5-2.2-.7-1.6-2.6-.5v-3.4l2.6-.5.7-1.6-1.5-2.2 2.4-2.4 2.2 1.5 1.6-.7z"/><circle cx="12" cy="12" r="3.2" fill="#fff"/></svg>
        </div>

        @if ($badge !== '')
            <div class="badge">{{ $badge }}</div>
        @endif

        <p class="text">{{ $text }}</p>

        @if ($email !== '')
            <p class="help">{{ $ar ? 'تحتاج مساعدة؟ راسلنا على' : 'Need help? Email us at' }} <a href="mailto:{{ $email }}" dir="ltr">{{ $email }}</a></p>
        @endif

        <div class="langs">
            <a href="{{ route('locale', 'en') }}" @class(['on' => !$ar])>English</a>
            <a href="{{ route('locale', 'ar') }}" @class(['on' => $ar])>العربية</a>
        </div>

        @if ($visits !== null)
            <div class="visits">{{ $ar ? 'عدد الزيارات' : 'Visits' }}: <b>{{ number_format($visits) }}</b></div>
        @endif

        <div class="foot">{{ $site }}</div>
    </main>
</body>
</html>
