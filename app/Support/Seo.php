<?php

namespace App\Support;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Everything search engines need to know about a public page: its address in each language, whether it may be
 * indexed, and the structured data (schema.org) printed in its head.
 *
 * Pages in LOCALIZED exist twice: "/terms" (English) and "/ar/terms" (Arabic, route name "ar.legal.terms").
 * Search engines send no cookies, so the language cookie alone would hide the Arabic site from them.
 */
class Seo
{
    /** Public pages with an English and an Arabic address. */
    public const LOCALIZED = ['home', 'legal.terms', 'legal.privacy', 'public.catalog', 'public.category', 'public.service'];

    /** Public pages with one address for both languages. */
    private const SINGLE = ['login', 'register'];

    /** A link to a public page in the visitor's language. */
    public static function url(string $name, mixed $params = [], ?string $locale = null): string
    {
        return route((($locale ?? app()->getLocale()) === 'ar' ? 'ar.' : '') . $name, $params);
    }

    public static function categoryUrl(ServiceCategory $category, ?string $locale = null): string
    {
        return self::url('public.category', ['category' => self::key($category)], $locale);
    }

    public static function serviceUrl(Service $service, ?string $locale = null): string
    {
        return self::url('public.service', ['service' => self::key($service)], $locale);
    }

    /** "3626-honor-frp-key": the id finds the record, so a renamed service keeps working (old names redirect). */
    public static function key(Service|ServiceCategory $record): string
    {
        $slug = (string) $record->slug;
        if (strlen($slug) > 70) {
            $cut = substr($slug, 0, 70);
            $slug = strrpos($cut, '-') ? substr($cut, 0, strrpos($cut, '-')) : $cut;
        }

        return $slug === '' ? (string) $record->id : $record->id . '-' . $slug;
    }

    /** Visitors may browse the services without signing in (Admin > Settings). */
    public static function catalogOpen(): bool
    {
        return setting('public_catalog') === '1';
    }

    /** Visitors see prices (Admin > Settings). */
    public static function showPrices(): bool
    {
        return setting('public_prices') === '1';
    }

    /** Test copies of the site (dev.unlock-sd.com, a local copy) must never compete with the real one in search results. */
    public static function privateHost(Request $request): bool
    {
        return app()->environment('local') || preg_match('/^(dev|test|staging)\./i', $request->getHost()) === 1;
    }

    /** May this address appear in search results? Everything that is not a public page may not. */
    public static function indexable(Request $request): bool
    {
        return !self::privateHost($request) && in_array(self::baseName($request), [...self::LOCALIZED, ...self::SINGLE], true);
    }

    /** The current route's name without the "ar." prefix. */
    private static function baseName(Request $request): string
    {
        $name = (string) $request->route()?->getName();

        return str_starts_with($name, 'ar.') ? substr($name, 3) : $name;
    }

    /** The one address search engines should remember for this page, in the language it is shown in. */
    public static function canonical(Request $request): ?string
    {
        $name = self::baseName($request);

        return match (true) {
            in_array($name, self::LOCALIZED, true) => self::address($request, $name, app()->getLocale()),
            in_array($name, self::SINGLE, true) => route($name),
            default => null,
        };
    }

    /** The same page in each language (hreflang), or an empty list for single-address pages. */
    public static function alternates(Request $request): array
    {
        $name = self::baseName($request);
        if (!in_array($name, self::LOCALIZED, true)) {
            return [];
        }
        $en = self::address($request, $name, 'en');

        return ['en' => $en, 'ar' => self::address($request, $name, 'ar'), 'x-default' => $en];
    }

    private static function address(Request $request, string $name, string $locale): string
    {
        // Only the parameters in the address itself: Route::view adds its own ("view", "data", ...).
        $params = array_intersect_key($request->route()->parameters(), array_flip($request->route()->parameterNames()));
        $url = self::url($name, $params, $locale);
        $page = $request->integer('page');

        return $name === 'public.category' && $page > 1 ? $url . '?page=' . $page : $url;
    }

    /** An admin's site text; the built-in default texts have an Arabic translation. */
    public static function text(string $key): string
    {
        $text = trim((string) setting($key));
        $translated = $text === '' ? '' : __($text);

        return is_string($translated) ? $translated : $text;
    }

    /** "seo_title" or, on Arabic pages, "seo_title_ar". Empty when the admin did not write one. */
    public static function custom(string $key): string
    {
        return trim((string) setting(app()->getLocale() === 'ar' ? $key . '_ar' : $key));
    }

    /** A service or category name without web addresses. */
    public static function name(?string $name): string
    {
        return self::tidy(self::stripUrls((string) $name));
    }

    /** A name for the page title: no emoji or decoration either. */
    public static function plainName(?string $name, int $limit = 70): string
    {
        $text = preg_replace('/[\p{So}\p{Sk}\x{FE0F}\x{200D}]+/u', ' ', self::name($name)) ?? (string) $name;

        return Str::limit(self::tidy($text), $limit, '…');
    }

    /** Removes web addresses from text: links to other sites never reach the pages search engines read. */
    public static function stripUrls(string $text): string
    {
        return preg_replace(
            ['~\b(?:https?|ftp)://\S+~iu', '~\bwww\.\S+~iu', '~(?<![\w@.-])(?:[a-z0-9-]+\.)+[a-z]{2,}/\S*~iu'],
            '',
            $text
        ) ?? $text;
    }

    private static function tidy(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /* ---------- Structured data ---------- */

    public static function organization(): array
    {
        $logo = (string) setting('site_logo');
        $email = trim((string) setting('contact_email'));
        $phone = trim((string) setting('contact_phone'));
        $telegram = setting('telegram_enabled') === '1' ? ltrim((string) setting('telegram_username'), '@') : '';

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => route('home') . '#organization',
            'name' => (string) setting('site_name'),
            'url' => route('home'),
            'logo' => $logo !== '' && is_file(public_path($logo)) ? asset($logo) : null,
            'description' => self::text('home_tagline') ?: null,
            'email' => $email ?: null,
            'telephone' => $phone ?: null,
            'contactPoint' => $email || $phone ? array_filter([
                '@type' => 'ContactPoint', 'contactType' => 'customer support',
                'email' => $email ?: null, 'telephone' => $phone ?: null, 'availableLanguage' => ['en', 'ar'],
            ]) : null,
            'sameAs' => $telegram !== '' ? ['https://t.me/' . $telegram] : null,
        ]);
    }

    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => (string) setting('site_name'),
            'url' => route('home'),
            'inLanguage' => ['en', 'ar'],
            'publisher' => ['@id' => route('home') . '#organization'],
        ];
    }

    /** @param array<array{0: string, 1: string}> $items [name, url] from the home page down to this page */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($item, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1, 'name' => $item[0], 'item' => $item[1],
            ], $items, array_keys($items)),
        ];
    }

    /** One service with its price, so search results can show it. Only real data: no invented ratings. */
    public static function product(Service $service, string $description): array
    {
        $url = self::serviceUrl($service);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => self::plainName($service->name, 150),
            'description' => $description,
            'sku' => (string) $service->id,
            'category' => self::name($service->category?->name),
            'url' => $url,
            'offers' => self::showPrices() ? [
                '@type' => 'Offer',
                'price' => number_format((float) $service->price, 2, '.', ''),
                'priceCurrency' => 'USD',
                'availability' => 'https://schema.org/InStock',
                'url' => $url,
                'seller' => ['@type' => 'Organization', 'name' => (string) setting('site_name')],
            ] : null,
        ]);
    }
}
