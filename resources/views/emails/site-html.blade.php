{{--
    Branded HTML email: navy header with the site's logo, a coloured strip with the heading, white body,
    details table, result code box and an orange button. Inline styles only, because email apps drop <style> rules.
--}}
@php
    $site = (string) (setting('site_name') ?: config('app.name'));
    // The header is navy, so the logo made for dark backgrounds is used first.
    $logoPath = (string) (setting('site_logo_wide_dark') ?: setting('site_logo_wide') ?: setting('site_logo'));
    $logoUrl = $logoPath !== '' && is_file(public_path($logoPath)) ? asset($logoPath) : null;
    $strip = ['success' => '#16a34a', 'danger' => '#dc2626', 'muted' => '#64748b'][$tone ?? ''] ?? '#ea580c';
    $font = "'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $mailSubject }}</title>
</head>
<body style="margin:0; padding:0; background:#eef2f7; font-family:{{ $font }}; color:#1f2a3c;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #dbe3ee;">
                {{-- Header: logo on navy --}}
                <tr>
                    <td align="center" style="background:#0f1d32; padding:22px 24px;">
                        <a href="{{ url('/') }}" style="text-decoration:none;">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $site }}" height="44" style="display:block; height:44px; width:auto; max-width:240px; border:0;">
                            @else
                                <span style="font:800 22px {{ $font }}; color:#ffffff;">{{ $site }}</span>
                            @endif
                        </a>
                    </td>
                </tr>
                {{-- Heading strip --}}
                <tr>
                    <td align="center" style="background:{{ $strip }}; padding:18px 24px;">
                        {{-- The title is the email's subject, so the inbox line and the email match. --}}
                        <h1 style="margin:0; font:700 22px/1.3 {{ $font }}; color:#ffffff;">{{ $mailSubject }}</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:26px 28px 8px;">
                        @foreach ($lines as $line)
                            <p style="margin:0 0 14px; font:16px/1.6 {{ $font }}; color:#334155;">{{ $line }}</p>
                        @endforeach

                        @if ($code !== null && $code !== '')
                            <p style="margin:18px 0 6px; font:700 13px {{ $font }}; color:#64748b; text-transform:uppercase; letter-spacing:.06em;">{{ $codeLabel ?: 'Result' }}</p>
                            <div style="padding:16px 18px; border-radius:12px; background:#f0fdf4; border:2px solid #16a34a; font:700 17px/1.6 Consolas, 'Courier New', monospace; color:#14532d; white-space:pre-wrap; word-break:break-word;">{{ $code }}</div>
                        @endif

                        @if ($details)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0 6px; border:1px solid #e2e8f0; border-radius:12px; border-collapse:separate; overflow:hidden;">
                                @foreach ($details as $label => $value)
                                    <tr>
                                        <td style="padding:11px 14px; width:38%; background:#f8fafc; font:600 14px {{ $font }}; color:#64748b; border-bottom:1px solid #e2e8f0; vertical-align:top;">{{ $label }}</td>
                                        <td style="padding:11px 14px; font:600 15px {{ $font }}; color:#0f1d32; border-bottom:1px solid #e2e8f0; word-break:break-word;">{{ $value }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif

                        @if ($buttonText && $buttonUrl)
                            <table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:24px auto 10px;">
                                <tr>
                                    <td style="border-radius:10px; background:#ea580c;">
                                        <a href="{{ $buttonUrl }}" style="display:inline-block; padding:13px 28px; font:700 16px {{ $font }}; color:#ffffff; text-decoration:none; border-radius:10px;">{{ $buttonText }}</a>
                                    </td>
                                </tr>
                            </table>
                        @endif
                    </td>
                </tr>
                {{-- Footer --}}
                <tr>
                    <td align="center" style="padding:18px 24px 22px; border-top:1px solid #eef2f7; font:13px/1.6 {{ $font }}; color:#94a3b8;">
                        <a href="{{ url('/') }}" style="color:#ea580c; font-weight:700; text-decoration:none;">{{ $site }}</a>
                        · {{ now()->year }}<br>
                        This email was sent automatically. Please do not reply to it.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
