<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Works out where a visitor came from: Facebook, WhatsApp, Telegram, Google...
 *
 * Order of evidence:
 *  1. A tag in the link (?ref=whatsapp or ?utm_source=facebook). The surest way: WhatsApp and Telegram
 *     apps usually open links with no referrer, so the admin shares tagged links there.
 *  2. The in-app browser (Facebook and Instagram apps announce themselves in the user agent).
 *  3. The referring site (facebook.com, t.me, google.com...).
 *  4. Nothing at all: "direct" (typed the address, a bookmark, or an app that hides the referrer).
 */
class VisitSourceService
{
    /** Source key => label shown to the admin. */
    public const SOURCES = [
        'facebook' => 'Facebook',
        'whatsapp' => 'WhatsApp',
        'telegram' => 'Telegram',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
        'twitter' => 'X (Twitter)',
        'snapchat' => 'Snapchat',
        'google' => 'Google',
        'bing' => 'Bing',
        'other' => 'Other websites',
        'direct' => 'Direct / unknown',
    ];

    /** Referrer host (or its ending) => source. */
    private const HOSTS = [
        'facebook.com' => 'facebook', 'fb.com' => 'facebook', 'fb.me' => 'facebook', 'messenger.com' => 'facebook',
        'whatsapp.com' => 'whatsapp', 'wa.me' => 'whatsapp',
        't.me' => 'telegram', 'telegram.org' => 'telegram', 'telegram.me' => 'telegram',
        'instagram.com' => 'instagram',
        'tiktok.com' => 'tiktok',
        'youtube.com' => 'youtube', 'youtu.be' => 'youtube',
        'twitter.com' => 'twitter', 'x.com' => 'twitter', 't.co' => 'twitter',
        'snapchat.com' => 'snapchat',
        'bing.com' => 'bing',
    ];

    /** Short tags people may put in ?ref= that mean the same source. */
    private const ALIASES = ['fb' => 'facebook', 'wa' => 'whatsapp', 'tg' => 'telegram', 'ig' => 'instagram', 'insta' => 'instagram', 'x' => 'twitter', 'yt' => 'youtube'];

    /** Link-preview fetchers and crawlers: not people, never counted. */
    private const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|facebookcatalog|whatsapp\/|telegram(bot)?\/|curl|wget|python|httpclient|headless|lighthouse|monitor/i';

    public function isBot(?string $userAgent): bool
    {
        return $userAgent === null || trim($userAgent) === '' || preg_match(self::BOTS, $userAgent) === 1;
    }

    /** @return array{0: string, 1: ?string} [source, referrer host] */
    public function detect(Request $request): array
    {
        $host = $this->referrerHost($request);

        $tag = strtolower(trim((string) ($request->query('ref') ?? $request->query('utm_source') ?? $request->query('src') ?? '')));
        if ($tag !== '') {
            $tag = self::ALIASES[$tag] ?? $tag;

            return [array_key_exists($tag, self::SOURCES) ? $tag : 'other', $host];
        }

        $agent = (string) $request->userAgent();
        if (preg_match('/FBAN|FBAV|FB_IAB|FBIOS|Messenger/i', $agent)) {
            return ['facebook', $host];
        }
        if (stripos($agent, 'Instagram') !== false) {
            return ['instagram', $host];
        }
        if (stripos($agent, 'Telegram') !== false) {
            return ['telegram', $host];
        }

        if ($host === null) {
            return ['direct', null];
        }

        foreach (self::HOSTS as $domain => $source) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return [$source, $host];
            }
        }

        // google.com, google.com.sa, google.sd...
        if (preg_match('/(^|\.)google\.[a-z.]+$/', $host)) {
            return ['google', $host];
        }

        return ['other', $host];
    }

    public function label(?string $source): string
    {
        return __(self::SOURCES[$source ?? 'direct'] ?? self::SOURCES['other']);
    }

    /** The referring site's host, or null when there is none or it is this site. */
    private function referrerHost(Request $request): ?string
    {
        $referer = (string) $request->headers->get('referer', '');
        $host = $referer !== '' ? strtolower((string) parse_url($referer, PHP_URL_HOST)) : '';
        $host = preg_replace('/^www\./', '', $host);

        if ($host === '' || $host === preg_replace('/^www\./', '', strtolower($request->getHost()))) {
            return null;
        }

        return mb_substr($host, 0, 120);
    }
}
