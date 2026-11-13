<?php

use App\Repositories\SettingRepository;

if (!function_exists('setting')) {
    /** Read a platform setting (cached), e.g. setting('site_name'). */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingRepository::class)->get($key, $default);
    }
}

if (!function_exists('bidi')) {
    /**
     * Keeps an amount such as "5$" in one piece inside Arabic (right-to-left) text.
     * It wraps the text in invisible Unicode "isolate" marks; nothing changes in English.
     */
    function bidi(string $text): string
    {
        return app()->getLocale() === 'ar' ? "\u{2068}" . $text . "\u{2069}" : $text;
    }
}

if (!function_exists('money')) {
    /** Format a credit amount with a trailing dollar sign, e.g. "125$". */
    function money(mixed $amount, bool $withUnit = true): string
    {
        return bidi(amount($amount) . '$');
    }
}

if (!function_exists('amount')) {
    /** A number without needless decimals: 5.00 becomes "5", 5.5 becomes "5.50". */
    function amount(mixed $amount): string
    {
        $value = round((float) $amount, 2);

        return number_format($value, floor($value) == $value ? 0 : 2);
    }
}

if (!function_exists('price')) {
    /** A service price as customers see it, e.g. "5$". */
    function price(mixed $amount): string
    {
        return bidi(amount($amount) . '$');
    }
}

if (!function_exists('rich_text')) {
    /** Safe HTML for a description written in the editor. Older plain-text descriptions keep their line breaks. */
    function rich_text(?string $html): \Illuminate\Support\HtmlString
    {
        $html = (string) $html;

        if ($html !== '' && $html === strip_tags($html)) {
            return new \Illuminate\Support\HtmlString(nl2br(e($html), false));
        }

        return new \Illuminate\Support\HtmlString((string) \App\Support\HtmlSanitizer::clean($html));
    }
}

if (!function_exists('plain_text')) {
    /** A description with its HTML removed, for one-line previews in lists. */
    function plain_text(?string $html, int $limit = 110): string
    {
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', $text)), $limit);
    }
}

if (!function_exists('to_cents')) {
    /** Credits are computed in whole cents so float rounding never touches a balance. */
    function to_cents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}

if (!function_exists('from_cents')) {
    function from_cents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
