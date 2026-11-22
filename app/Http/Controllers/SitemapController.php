<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\Seo;
use Illuminate\Http\Request;

/** robots.txt and sitemap.xml. Outside the "web" middleware: no session, no cookies, open during maintenance. */
class SitemapController extends Controller
{
    /** Private areas: nothing to find there, so crawlers spend their time on the public pages. */
    private const DISALLOW = [
        '/admin', '/dashboard', '/services', '/requests', '/credits', '/profile', '/guide',
        '/language/', '/email/', '/reset-password/', '/provider-callback/', '/api/',
    ];

    public function robots(Request $request)
    {
        $lines = ['User-agent: *'];
        if (Seo::privateHost($request)) {
            // The dev site stays crawlable so search engines can read its "noindex" and drop it.
            $lines[] = 'Disallow:';
        } else {
            foreach (self::DISALLOW as $path) {
                $lines[] = 'Disallow: ' . $request->getBasePath() . $path;
            }
            $lines[] = '';
            $lines[] = 'Sitemap: ' . route('sitemap');
        }

        return response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(Request $request)
    {
        abort_if(Seo::privateHost($request), 404);

        $entries = [
            [fn ($l) => Seo::url('home', [], $l), null],
            [fn ($l) => Seo::url('legal.terms', [], $l), null],
            [fn ($l) => Seo::url('legal.privacy', [], $l), null],
        ];

        if (Seo::catalogOpen()) {
            $entries[] = [fn ($l) => Seo::url('public.catalog', [], $l), null];

            $categories = ServiceCategory::where('status', 'active')
                ->whereHas('services', fn ($q) => $q->where('status', 'active'))
                ->orderBy('sort_order')->orderBy('name')->get(['id', 'slug', 'updated_at']);
            foreach ($categories as $category) {
                $entries[] = [fn ($l) => Seo::categoryUrl($category, $l), $category->updated_at];
            }

            $services = Service::where('status', 'active')->whereIn('service_category_id', $categories->modelKeys())
                ->orderBy('id')->get(['id', 'slug', 'updated_at']);
            foreach ($services as $service) {
                $entries[] = [fn ($l) => Seo::serviceUrl($service, $l), $service->updated_at];
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach ($entries as [$url, $updated]) {
            $en = e($url('en'));
            $ar = e($url('ar'));
            $tail = ($updated ? '<lastmod>' . $updated->toAtomString() . '</lastmod>' : '')
                . '<xhtml:link rel="alternate" hreflang="en" href="' . $en . '"/>'
                . '<xhtml:link rel="alternate" hreflang="ar" href="' . $ar . '"/>'
                . '<xhtml:link rel="alternate" hreflang="x-default" href="' . $en . '"/>' . "</url>\n";
            $xml .= '<url><loc>' . $en . '</loc>' . $tail . '<url><loc>' . $ar . '</loc>' . $tail;
        }

        return response($xml . "</urlset>\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
