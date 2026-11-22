<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\HtmlSanitizer;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The services as visitors and search engines see them, without signing in (Admin > Settings > Search engines).
 * Customers are sent to the same page in their own area, where their price and the order form are.
 */
class CatalogController extends Controller
{
    /** Services per category page. */
    public const PER_PAGE = 60;

    public function index(Request $request)
    {
        if ($away = $this->elsewhere($request, route('customer.services.index'))) {
            return $away;
        }

        $categories = ServiceCategory::where('status', 'active')
            ->withCount(['services' => fn ($q) => $q->where('status', 'active')])
            ->withMin(['services' => fn ($q) => $q->where('status', 'active')], 'price')
            ->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn ($c) => $c->services_count > 0)->values();

        $site = (string) setting('site_name');

        return view('public.catalog', [
            'categories' => $categories,
            'seo' => [
                'title' => __('All unlocking services and prices') . ' · ' . $site,
                'description' => __('Browse :count unlocking services by IMEI, server and remote on :site, with delivery times. Create an account and order online.', [
                    'count' => number_format($categories->sum('services_count')), 'site' => $site,
                ]),
                'jsonld' => [Seo::breadcrumbs([
                    [$site, Seo::url('home')],
                    [__('Services'), Seo::url('public.catalog')],
                ])],
            ],
        ]);
    }

    public function category(Request $request, string $category)
    {
        $record = ServiceCategory::where('status', 'active')->findOrFail((int) $category);

        if ($away = $this->elsewhere($request, route('customer.services.index', ['category' => $record->id]))) {
            return $away;
        }
        if ($category !== Seo::key($record)) {
            return redirect(Seo::categoryUrl($record), 301);
        }

        $q = $request->query('q');
        $q = is_string($q) ? mb_substr(trim($q), 0, 80) : '';
        $services = $record->services()->where('status', 'active')
            ->when($q !== '', fn ($w) => $w->where('name', 'like', '%' . addcslashes($q, '%_\\') . '%'))
            ->orderBy('name')->orderBy('id')
            ->paginate(self::PER_PAGE, ['id', 'service_category_id', 'name', 'slug', 'price', 'delivery_time', 'is_instant', 'created_at'])
            ->withQueryString();
        abort_if($services->currentPage() > $services->lastPage(), 404);

        $site = (string) setting('site_name');
        $name = Seo::name($record->name);
        $page = $services->currentPage() > 1 ? ' · ' . __('Page :page', ['page' => $services->currentPage()]) : '';

        return view('public.category', [
            'category' => $record,
            'name' => $name,
            'services' => $services,
            // Under the same headings as the customer catalog: the part of the name before "|".
            'groups' => $services->getCollection()->groupBy(fn ($s) => Service::groupOf($s->name, $record->name)),
            'q' => $q,
            'seo' => [
                'title' => Seo::plainName($record->name, 50) . ' · ' . __('services and prices') . $page . ' · ' . $site,
                'description' => Str::limit(Seo::name(plain_text($record->description, 300)) ?: __(':name: :count services with delivery times on :site. Create an account and order online.', [
                    'name' => $name, 'count' => number_format($services->total()), 'site' => $site,
                ]), 155) . $page,
                // Search results and empty categories are not pages of their own.
                'noindex' => $q !== '' || $services->total() === 0,
                'jsonld' => [Seo::breadcrumbs([
                    [$site, Seo::url('home')],
                    [__('Services'), Seo::url('public.catalog')],
                    [$name, Seo::categoryUrl($record)],
                ])],
            ],
        ]);
    }

    public function service(Request $request, string $service)
    {
        $record = Service::with('category')->where('status', 'active')->findOrFail((int) $service);
        abort_unless($record->isAvailable() && !$record->isArchived(), 404);

        if ($away = $this->elsewhere($request, route('customer.services.show', $record->id))) {
            return $away;
        }
        if ($service !== Seo::key($record)) {
            return redirect(Seo::serviceUrl($record), 301);
        }

        $site = (string) setting('site_name');
        $name = Seo::name($record->name);
        $category = Seo::name($record->category->name);
        // Providers' descriptions often link to other sites and downloads. Visitors get the text without those links.
        $description = HtmlSanitizer::forVisitors($record->description, $request->getHost());
        $delivery = Seo::name($record->delivery_time);

        $facts = array_filter([
            Seo::plainName($record->name, 80),
            Seo::showPrices() ? __('Price') . ': ' . price($record->price) : null,
            $delivery !== '' ? __('Delivery time') . ': ' . Str::limit($delivery, 40) : null,
            plain_text($description, 160) ?: __('Order online at :site.', ['site' => $site]),
        ]);
        $summary = Str::limit(implode(' · ', $facts), 160);

        return view('public.service', [
            'service' => $record,
            'name' => $name,
            'category' => $category,
            'description' => $description,
            'delivery' => $delivery,
            'fields' => $record->inputFields(),
            'related' => $this->related($record),
            'seo' => [
                'title' => Seo::plainName($record->name, 65) . ' · ' . $site,
                'description' => $summary,
                'jsonld' => [
                    Seo::product($record, $summary),
                    Seo::breadcrumbs([
                        [$site, Seo::url('home')],
                        [__('Services'), Seo::url('public.catalog')],
                        [$category, Seo::categoryUrl($record->category)],
                        [$name, Seo::serviceUrl($record)],
                    ]),
                ],
            ],
        ]);
    }

    /** Where to send someone this page is not for: customers to their own area, visitors to sign in when the catalog is closed. */
    private function elsewhere(Request $request, string $customerUrl)
    {
        $user = $request->user();

        return match (true) {
            $user && !$user->isAdmin() => redirect($customerUrl),
            !$user && !Seo::catalogOpen() => redirect()->guest(route('login')),
            default => null,
        };
    }

    /** Up to 8 other services: the same group ("Honor FRP | ...") first, then neighbours in the category. */
    private function related(Service $service)
    {
        $base = fn () => Service::where('status', 'active')->where('service_category_id', $service->service_category_id)
            ->whereKeyNot($service->id)->select(['id', 'service_category_id', 'name', 'slug', 'price', 'delivery_time', 'is_instant', 'created_at']);

        $pos = mb_strpos($service->name, '|');
        $head = $pos ? trim(mb_substr($service->name, 0, $pos)) : '';
        $same = $head !== ''
            ? $base()->where('name', 'like', addcslashes($head, '%_\\') . '%')->orderBy('name')->limit(8)->get()
            : collect();

        if ($same->count() < 8) {
            $same = $same->concat($base()->where('name', '>', $service->name)->whereNotIn('id', $same->pluck('id'))
                ->orderBy('name')->limit(8 - $same->count())->get());
        }
        if ($same->count() < 8) {
            $same = $same->concat($base()->where('name', '<=', $service->name)->whereNotIn('id', $same->pluck('id'))
                ->orderByDesc('name')->limit(8 - $same->count())->get());
        }

        return $same;
    }
}
