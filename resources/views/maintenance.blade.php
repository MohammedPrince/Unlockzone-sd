{{--
    Maintenance page shown to visitors and customers while Admin > Settings > Maintenance mode is on.
    Self-contained (own styles) so it works even if the site's assets are being updated.
    Text follows the visitor's language (English / Arabic switch at the bottom).
--}}
@php
    $ar = app()->getLocale() === 'ar';
    $site = (string) setting('site_name');
    $logoWide = (string) setting('site_logo_wide');
    $logoWideDark = (string) setting('site_logo_wide_dark') ?: $logoWide;
    $logo = (string) setting('site_logo');

    // Launch time (Settings > Maintenance mode > Back online at): countdown + date. Passed in by the middleware.
    // With a launch time the page is an "opening" page: rocket, big headline, launch message, perks, countdown.
    $endsAt = isset($endsAt) && $endsAt && $endsAt->isFuture() ? $endsAt : null;
    $launching = $endsAt !== null;

    $badge = trim((string) setting($ar ? 'maintenance_badge_ar' : 'maintenance_badge'));
    $headline = trim((string) setting($ar ? 'maintenance_headline_ar' : 'maintenance_headline'));
    if ($headline === '' && $launching) {
        $headline = $ar ? '🚀 الافتتاح الكبير اقترب!' : '🚀 The Grand Opening Is Almost Here!';
    }
    $text = trim((string) setting($ar ? 'maintenance_text_ar' : 'maintenance_text'));
    if ($text === '') {
        $text = match (true) {
            $launching && $ar => "{$site} يفتح أبوابه بتجربة جديدة كليًا: فك أسرع، تسليم فوري، وأفضل الأسعار في السوق.\n\nاحفظ الموعد وكن من أوائل من يطلب لحظة الإطلاق!",
            $launching => "{$site} is opening its doors with a brand-new experience: faster unlocking, instant delivery and the best prices in the market.\n\nMark the date and be among the first to order the moment we go live!",
            $ar => "نعمل الآن على تحديث {$site} لنخدمك بشكل أفضل.\n\nسنعود قريبًا جدًا. شكرًا لصبرك.",
            default => "We are updating {$site} to serve you better.\n\nWe will be back very soon. Thank you for your patience.",
        };
    }
    $perks = $ar
        ? ['⚡ تسليم فوري', '💰 أفضل الأسعار', '🛡️ آمن وموثوق', '🕐 دعم 24/7']
        : ['⚡ Instant delivery', '💰 Best prices', '🛡️ Safe & trusted', '🕐 24/7 support'];
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
    {{-- A shared link still shows the site name, tagline and logo, as when the site is open. --}}
    @include('partials.share-meta')
    @include('partials.favicon')
    <title>{{ $ar ? 'الموقع تحت الصيانة' : 'Under maintenance' }} · {{ $site }}</title>
    {{-- Same theme rule as the rest of the site: dark by default, light if the visitor chose it. --}}
    @include('partials.head-theme')
    <style>
        /* Dark (default) */
        :root {
            --orange: #f97316; --text: #c4d2e6; --muted: #8fa4c0; --heading: #e7eef8;
            --page: #0a1424; --glow-1: rgba(249, 115, 22, .16); --glow-2: rgba(76, 154, 255, .08);
            --card: #0f1d32; --card-line: #213652; --shadow: 0 30px 80px -30px rgba(0, 0, 0, .7);
            --icon-bg: rgba(249, 115, 22, .14); --badge-bg: rgba(249, 115, 22, .16); --badge-text: #fdba8c;
            --langs-line: #213652; --langs-text: #8fa4c0; --on-bg: #ea580c; --on-text: #fff; --gear-line: #0a1424;
            color-scheme: dark;
        }
        /* Light (the visitor switched the site to light) */
        :root[data-theme=light] {
            --orange: #f26b1d; --text: #4b5563; --muted: #8a94a6; --heading: #0b2545;
            --page: #fbfaf8; --glow-1: #ffe9c7; --glow-2: #fff4e4;
            --card: #fff; --card-line: transparent; --shadow: 0 30px 80px -30px rgba(11, 37, 69, .25), 0 2px 6px rgba(11, 37, 69, .04);
            --icon-bg: #fdf0e1; --badge-bg: #fdebdc; --badge-text: #f26b1d;
            --langs-line: #e7e9ee; --langs-text: #5b6475; --on-bg: #0b2545; --on-text: #fff; --gear-line: #0b2545;
            color-scheme: light;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 16px;
            font: 16px/1.7 "Segoe UI", system-ui, -apple-system, Roboto, Tahoma, Arial, sans-serif; color: var(--text);
            background: radial-gradient(circle at 92% 8%, var(--glow-1) 0, transparent 42%), radial-gradient(circle at 0% 100%, var(--glow-2) 0, transparent 40%), var(--page);
        }
        .card {
            position: relative; width: 100%; max-width: 700px; background: var(--card); border: 1px solid var(--card-line);
            border-radius: 32px; padding: 56px 44px 34px; text-align: center; box-shadow: var(--shadow);
        }
        .logo img { max-height: 64px; max-width: 220px; }
        .logo .name { font-size: 22px; font-weight: 800; color: var(--heading); }
        .logo .for-light { display: none; }
        :root[data-theme=light] .logo .for-light { display: inline; }
        :root[data-theme=light] .logo .for-dark { display: none; }
        .icon {
            width: 80px; height: 80px; margin: 28px auto 24px; border-radius: 50%; background: var(--icon-bg);
            display: flex; align-items: center; justify-content: center; font-size: 40px;
        }
        .icon svg { width: 44px; height: 44px; stroke: var(--gear-line); }
        .badge {
            display: inline-block; padding: 7px 16px; border-radius: 999px; background: var(--badge-bg); color: var(--badge-text);
            font-weight: 700; font-size: 14px;
        }
        .text { margin: 26px 0 0; white-space: pre-line; font-size: 17px; }
        /* Countdown to the launch time */
        .launch { margin-top: 30px; }
        .launch-label { font-size: 14px; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; }
        .launch-date { margin-top: 4px; font-size: 19px; font-weight: 700; color: var(--heading); font-variant-numeric: tabular-nums; }
        .count { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; max-width: 460px; margin: 18px auto 0; direction: ltr; }
        .count div { padding: 14px 6px 10px; border-radius: 18px; background: var(--icon-bg); border: 1px solid var(--langs-line); }
        .count b { display: block; font-size: 34px; line-height: 1.1; color: var(--orange); font-variant-numeric: tabular-nums; }
        .count span { display: block; margin-top: 4px; font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }
        :root[dir=rtl] .count span, [dir=rtl] .count span { letter-spacing: 0; }
        .launch-live { margin-top: 18px; font-size: 18px; font-weight: 700; color: var(--orange); }
        /* Opening page (a launch time is set) */
        .headline {
            margin: 22px 0 0; font-size: clamp(28px, 5vw, 42px); line-height: 1.15; font-weight: 900; letter-spacing: -.01em;
            background: linear-gradient(90deg, #fff 0%, #fdba74 45%, #f97316 75%, #fbbf24 100%);
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        :root[data-theme=light] .headline { background-image: linear-gradient(90deg, #0b2545 0%, #c2410c 55%, #f97316 100%); }
        .headline .emoji { -webkit-text-fill-color: initial; color: initial; }
        .launching .card { border-color: rgba(249, 115, 22, .55); animation: card-glow 3s ease-in-out infinite; }
        @keyframes card-glow {
            0%, 100% { box-shadow: var(--shadow), 0 0 0 0 rgba(249, 115, 22, 0); }
            50% { box-shadow: var(--shadow), 0 0 60px -6px rgba(249, 115, 22, .45); }
        }
        .launching .icon { background: linear-gradient(135deg, rgba(239, 68, 68, .25), rgba(249, 115, 22, .3)); animation: lift 2.4s ease-in-out infinite; font-size: 42px; }
        @keyframes lift { 50% { transform: translateY(-6px); } }
        .perks { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-top: 22px; padding: 0; list-style: none; }
        .perks li { padding: 7px 14px; border-radius: 999px; border: 1px solid var(--langs-line); background: var(--icon-bg); color: var(--heading); font-size: 14px; font-weight: 700; }
        .launching .launch { margin-top: 22px; padding: 22px 16px 20px; border-radius: 22px; border: 1px solid rgba(249, 115, 22, .35); background: linear-gradient(180deg, rgba(249, 115, 22, .08), transparent); }
        .launching .launch-label { color: #f87171; font-weight: 800; }
        @media (prefers-reduced-motion: reduce) { .launching .card, .launching .icon { animation: none; } }
        .help { margin-top: 30px; font-size: 15px; }
        .help a { color: var(--orange); font-weight: 700; }
        .langs { display: inline-flex; gap: 4px; margin-top: 22px; padding: 5px; border: 1px solid var(--langs-line); border-radius: 14px; }
        .langs a { padding: 8px 18px; border-radius: 10px; color: var(--langs-text); font-weight: 700; text-decoration: none; font-size: 15px; }
        .langs a.on { background: var(--on-bg); color: var(--on-text); }
        .visits { margin-top: 26px; font-size: 22px; color: var(--heading); }
        .foot { margin-top: 26px; font-size: 13px; color: var(--muted); }
        /* Light / dark switch in the card's corner */
        .theme {
            position: absolute; top: 18px; inset-inline-end: 18px; width: 40px; height: 40px; display: grid; place-items: center;
            border: 1px solid var(--langs-line); border-radius: 50%; background: transparent; color: var(--muted); cursor: pointer;
        }
        .theme:hover { color: var(--orange); border-color: var(--orange); }
        .theme:focus-visible { outline: 2px solid var(--orange); outline-offset: 2px; }
        .theme svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
        .theme .moon, :root[data-theme=light] .theme .sun { display: none; }
        :root[data-theme=light] .theme .moon { display: block; }
        @media (max-width: 520px) { .card { padding: 40px 20px 26px; border-radius: 24px; } .text { font-size: 16px; } .count { gap: 8px; } .count b { font-size: 26px; } .count span { font-size: 10px; } .theme { top: 12px; inset-inline-end: 12px; } }
    </style>
</head>
<body @class(['launching' => $launching])>
    <main class="card">
        <button type="button" class="theme" data-theme-toggle title="{{ $ar ? 'فاتح / داكن' : 'Switch light / dark' }}" aria-label="{{ $ar ? 'فاتح / داكن' : 'Switch light / dark' }}">
            <svg class="sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            <svg class="moon" viewBox="0 0 24 24"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
        </button>

        <div class="logo">
            @if ($logoWide !== '')
                <img class="for-dark" src="{{ asset($logoWideDark) }}" alt="{{ $site }}">
                <img class="for-light" src="{{ asset($logoWide) }}" alt="">
            @elseif ($logo !== '')
                <img src="{{ asset($logo) }}" alt="{{ $site }}">
            @else
                <span class="name">{{ $site }}</span>
            @endif
        </div>

        <div class="icon" aria-hidden="true">
            @if ($launching)
                🚀
            @else
            {{-- A gear: "work in progress". --}}
            <svg viewBox="0 0 24 24" fill="#f5b400" stroke="#0b2545" stroke-width="1.4" stroke-linejoin="round"><path d="M10.3 2.5h3.4l.5 2.6 1.6.7 2.2-1.5 2.4 2.4-1.5 2.2.7 1.6 2.6.5v3.4l-2.6.5-.7 1.6 1.5 2.2-2.4 2.4-2.2-1.5-1.6.7-.5 2.6h-3.4l-.5-2.6-1.6-.7-2.2 1.5-2.4-2.4 1.5-2.2-.7-1.6-2.6-.5v-3.4l2.6-.5.7-1.6-1.5-2.2 2.4-2.4 2.2 1.5 1.6-.7z"/><circle cx="12" cy="12" r="3.2" fill="#fff"/></svg>
            @endif
        </div>

        {{-- The countdown sits right under the rocket, so visitors see it first (also on phones). --}}
        @if ($endsAt)
            @php $left = max(0, (int) now()->diffInSeconds($endsAt, false)); @endphp
            {{-- Server-side numbers are right on first paint; the script below keeps them ticking and shows the
                 date in the visitor's own time zone. At zero the page reloads and the site is open again. --}}
            <section class="launch" data-launch="{{ $endsAt->toIso8601String() }}" data-left="{{ $left }}">
                <div class="launch-label">{{ $ar ? 'سيكون الموقع متاحًا في' : 'Will be live on' }}</div>
                <div class="launch-date" dir="ltr" data-launch-date>{{ $endsAt->format('m/d/Y h:i A') }} (GMT{{ $endsAt->format('P') }})</div>
                <div class="count" role="timer" aria-live="off">
                    <div><b data-unit="d">{{ sprintf('%02d', intdiv($left, 86400)) }}</b><span>{{ $ar ? 'يوم' : 'Days' }}</span></div>
                    <div><b data-unit="h">{{ sprintf('%02d', intdiv($left % 86400, 3600)) }}</b><span>{{ $ar ? 'ساعة' : 'Hours' }}</span></div>
                    <div><b data-unit="m">{{ sprintf('%02d', intdiv($left % 3600, 60)) }}</b><span>{{ $ar ? 'دقيقة' : 'Minutes' }}</span></div>
                    <div><b data-unit="s">{{ sprintf('%02d', $left % 60) }}</b><span>{{ $ar ? 'ثانية' : 'Seconds' }}</span></div>
                </div>
                <div class="launch-live" data-launch-live hidden>{{ $ar ? 'الموقع متاح الآن! جارٍ التحميل…' : 'We are live! Loading…' }}</div>
            </section>
        @endif

        @if ($badge !== '')
            <div class="badge">{{ $badge }}</div>
        @endif

        @if ($headline !== '')
            {{-- Emoji keep their own colours inside the gradient text. --}}
            <h1 class="headline">{!! preg_replace('/(\p{Extended_Pictographic}\x{FE0F}?)/u', '<span class="emoji">$1</span>', e($headline)) !!}</h1>
        @endif

        <p class="text">{{ $text }}</p>

        @if ($launching)
            <ul class="perks">
                @foreach ($perks as $perk) <li>{{ $perk }}</li> @endforeach
            </ul>
        @endif


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
    <script>
        // Same storage key as the main site, so the choice carries over once the site is back.
        document.querySelector('[data-theme-toggle]').addEventListener('click', function () {
            var light = document.documentElement.getAttribute('data-theme') !== 'light';
            if (light) { document.documentElement.setAttribute('data-theme', 'light'); } else { document.documentElement.removeAttribute('data-theme'); }
            try { localStorage.setItem('theme', light ? 'light' : 'dark'); } catch (e) { /* storage blocked */ }
        });

        // Countdown to the launch time; at zero the page reloads and the server lets the visitor in.
        (function () {
            var box = document.querySelector('[data-launch]');
            if (!box) { return; }
            // Counted from the server's "seconds left", so a visitor whose clock is wrong still sees the right time.
            var end = Date.now() + parseInt(box.getAttribute('data-left'), 10) * 1000;
            if (isNaN(end)) { return; }
            // The date in the visitor's own time zone, e.g. "11/21/2026, 12:00 AM GMT+2".
            try {
                box.querySelector('[data-launch-date]').textContent = new Date(box.getAttribute('data-launch')).toLocaleString('en-US', {
                    month: '2-digit', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true, timeZoneName: 'short'
                });
            } catch (e) { /* keep the server's text */ }
            var units = {};
            ['d', 'h', 'm', 's'].forEach(function (u) { units[u] = box.querySelector('[data-unit="' + u + '"]'); });
            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            var timer;
            var tick = function () {
                var left = Math.max(0, Math.floor((end - Date.now()) / 1000));
                units.d.textContent = pad(Math.floor(left / 86400));
                units.h.textContent = pad(Math.floor(left % 86400 / 3600));
                units.m.textContent = pad(Math.floor(left % 3600 / 60));
                units.s.textContent = pad(left % 60);
                if (left === 0) {
                    clearInterval(timer);
                    box.querySelector('[data-launch-live]').hidden = false;
                    // A moment's grace for clocks that run slightly fast; the server decides when it opens.
                    setTimeout(function () { location.reload(); }, 3000);
                }
            };
            timer = setInterval(tick, 1000);
            tick();
        })();
    </script>
</body>
</html>
