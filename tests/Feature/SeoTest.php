<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Repositories\SettingRepository;
use App\Support\HtmlSanitizer;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Search engines: public service pages, Arabic addresses, canonical / hreflang, robots.txt, sitemap.xml, noindex. */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function customer(): User
    {
        $user = new User([
            'first_name' => 'Ali', 'last_name' => 'Test', 'name' => 'Ali Test',
            'username' => 'ali', 'email' => 'ali@example.com', 'password' => 'secret-pass-1',
        ]);
        $user->role = 'customer';
        $user->status = 'active';
        $user->save();

        return $user;
    }

    private function service(): Service
    {
        return Service::where('slug', 'haafedk-test-1')->firstOrFail();
    }

    private function settings(array $values): void
    {
        app(SettingRepository::class)->setMany($values);
    }

    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return collect($m[1])->map(fn ($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR))->keyBy('@type')->all();
    }

    public function test_home_page_has_canonical_languages_structured_data_and_custom_texts(): void
    {
        $this->settings(['site_name' => 'Unlock-SD', 'contact_email' => 'help@example.com']);

        $html = $this->get('/')->assertOk()
            ->assertSee('<link rel="canonical" href="' . route('home') . '">', false)
            ->assertSee('<link rel="alternate" hreflang="ar" href="' . route('ar.home') . '">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="' . route('home') . '">', false)
            ->assertSee(Seo::categoryUrl($this->service()->category), false)
            ->assertHeaderMissing('X-Robots-Tag')
            ->getContent();
        $graphs = $this->jsonLd($html);
        $this->assertSame('Unlock-SD', $graphs['Organization']['name']);
        $this->assertSame('help@example.com', $graphs['Organization']['contactPoint']['email']);
        $this->assertArrayHasKey('WebSite', $graphs);
        $this->assertSame(1, substr_count($html, '<h1'));

        $this->settings(['seo_title' => 'IMEI unlocking', 'seo_description' => 'Unlock "any" phone']);
        $this->get('/')->assertSee('<title>IMEI unlocking</title>', false)
            ->assertSee('<meta name="description" content="Unlock &quot;any&quot; phone">', false);
    }

    public function test_arabic_pages_have_their_own_addresses_and_links(): void
    {
        $this->get('/ar')->assertOk()->assertSee('lang="ar" dir="rtl"', false)
            ->assertSee('<link rel="canonical" href="' . route('ar.home') . '">', false)
            ->assertSee(route('ar.public.catalog'), false)
            ->assertSee(route('ar.legal.terms'), false)
            ->assertCookie('locale', 'ar');
        $this->get('/ar/terms')->assertOk()->assertSee('شروط الخدمة')
            ->assertSee('<link rel="alternate" hreflang="en" href="' . route('legal.terms') . '">', false);
        $this->get(Seo::serviceUrl($this->service(), 'ar'))->assertOk()->assertSee('اطلب الآن')
            ->assertSee('<link rel="alternate" hreflang="en" href="' . Seo::serviceUrl($this->service(), 'en') . '">', false);
    }

    public function test_language_switch_leaves_the_arabic_address(): void
    {
        $this->from(url('/ar/catalog'))->get('/language/en')->assertRedirect(url('/catalog'))->assertCookie('locale', 'en');
        $this->from(url('/ar'))->get('/language/en')->assertRedirect(url('/') . '/');
        $this->from(url('/ar?page=2'))->get('/language/en')->assertRedirect(url('/') . '/?page=2');
        $this->from(url('/article'))->get('/language/en')->assertRedirect(url('/article'));
        $this->from(url('/login'))->get('/language/ar')->assertRedirect(url('/login'))->assertCookie('locale', 'ar');
    }

    public function test_service_page_for_visitors(): void
    {
        $service = $this->service();
        $html = $this->get(Seo::serviceUrl($service))->assertOk()
            ->assertSee($service->name)->assertSee('5$')
            ->assertSee(route('customer.services.show', $service->id), false)
            ->assertSee('<link rel="canonical" href="' . Seo::serviceUrl($service) . '">', false)
            ->assertHeaderMissing('X-Robots-Tag')->getContent();
        $graphs = $this->jsonLd($html);
        $this->assertSame('5.00', $graphs['Product']['offers']['price']);
        $this->assertSame('USD', $graphs['Product']['offers']['priceCurrency']);
        $this->assertArrayNotHasKey('aggregateRating', $graphs['Product']);
        $this->assertCount(4, $graphs['BreadcrumbList']['itemListElement']);
        $this->assertSame(1, substr_count($html, '<h1'));

        // An old or shortened address moves to the current one; unknown, inactive or archived services do not exist.
        $this->get('/service/' . $service->id)->assertStatus(301)->assertRedirect(Seo::serviceUrl($service));
        $this->get('/service/' . $service->id . '-old-name')->assertStatus(301);
        $this->get('/service/999999')->assertNotFound();
        $service->update(['status' => 'inactive']);
        $this->get(Seo::serviceUrl($service))->assertNotFound();
        $service->update(['status' => 'active']);
        $service->category->update(['status' => 'inactive']);
        $this->get(Seo::serviceUrl($service))->assertNotFound();
    }

    public function test_ordering_from_a_public_page_comes_back_to_the_order_form_after_sign_in(): void
    {
        $this->customer();
        $order = route('customer.services.show', $this->service()->id);

        $this->get($order)->assertRedirect(route('login'));
        $this->post(route('login.submit'), ['login' => 'ali', 'password' => 'secret-pass-1'])->assertRedirect($order);
    }

    public function test_links_and_pictures_from_other_sites_never_reach_the_public_page(): void
    {
        $service = $this->service();
        $service->update(['description' => '<h1>Info</h1><p>Read <a href="https://evil.example/tool.exe">this guide</a> or www.evil.example/x and mega.nz/file/abc'
            . ' for iOS 17.1/17.2 &amp; A12/A13</p><p><a href="/terms">our terms</a></p><img src="https://cdn.evil.example/a.png"><img src="/uploads/services/ok.png" alt="ok">']);

        $this->get(Seo::serviceUrl($service))->assertOk()
            ->assertSee('this guide')->assertSee('iOS 17.1/17.2 &amp; A12/A13', false)
            ->assertSee('href="/terms"', false)->assertSee('/uploads/services/ok.png', false)
            ->assertDontSee('evil.example', false)->assertDontSee('mega.nz', false)
            ->assertDontSee('<h1>Info', false)->assertSee('<h2>Info', false);

        $this->assertSame('', HtmlSanitizer::forVisitors(null, 'localhost'));
        $this->assertSame('', HtmlSanitizer::forVisitors('<a href="https://evil.example/x">https://evil.example/x</a>', 'localhost'));
        // Customers still see the full description.
        $this->actingAs($this->customer())->get(route('customer.services.show', $service->id))->assertOk()->assertSee('evil.example', false);
    }

    public function test_a_name_cannot_break_out_of_the_structured_data(): void
    {
        $service = $this->service();
        $service->update(['name' => 'Bad </script><script>alert(1)</script> & "name"']);

        $html = $this->get(Seo::serviceUrl($service))->assertOk()->assertDontSee('<script>alert(1)', false)->getContent();
        $this->assertStringContainsString('</script><script>', $this->jsonLd($html)['Product']['name']);
    }

    public function test_category_page_lists_pages_and_searches(): void
    {
        $category = ServiceCategory::create(['name' => 'Bulk', 'slug' => 'bulk', 'status' => 'active', 'sort_order' => 9]);
        for ($i = 1; $i <= 65; $i++) {
            Service::create([
                'service_category_id' => $category->id, 'name' => sprintf('Honor %02d', $i), 'slug' => "honor-{$i}",
                'price' => '1.00', 'status' => 'active', 'configuration' => ['field_ids' => []],
            ]);
        }
        $url = Seo::categoryUrl($category);

        $this->get(route('public.catalog'))->assertOk()->assertSee($url, false);
        $this->get($url)->assertOk()->assertSee('Honor 01')->assertDontSee('Honor 65')->assertSee('<link rel="canonical" href="' . $url . '">', false);
        $this->get($url . '?page=2')->assertOk()->assertSee('Honor 65')->assertSee('<link rel="canonical" href="' . $url . '?page=2">', false);
        $this->get($url . '?page=3')->assertNotFound();
        // Search results are not indexed.
        $this->get($url . '?q=Honor+07')->assertOk()->assertSee('Honor 07')->assertDontSee('Honor 08')
            ->assertSee('content="noindex, follow"', false)->assertDontSee('rel="canonical"', false);
        $this->get($url . '?q=%25')->assertOk()->assertDontSee('Honor 01');
        $this->get($url . '?q[]=x')->assertOk();
        $this->get('/catalog/' . $category->id)->assertStatus(301)->assertRedirect($url);
    }

    public function test_switches_close_the_catalog_and_hide_prices(): void
    {
        $service = $this->service();

        $this->settings(['public_prices' => '0']);
        $html = $this->get(Seo::serviceUrl($service))->assertOk()->assertDontSee('5$')->getContent();
        $this->assertArrayNotHasKey('offers', $this->jsonLd($html)['Product']);
        $this->get('/')->assertDontSee('5$');
        $this->get(Seo::categoryUrl($service->category))->assertDontSee('5$');

        $this->settings(['public_catalog' => '0']);
        $this->get(Seo::serviceUrl($service))->assertRedirect(route('login'));
        $this->get(route('public.catalog'))->assertRedirect(route('login'));
        $this->get('/')->assertOk()->assertDontSee('/service/', false)->assertDontSee('/catalog', false);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/service/', false);
    }

    public function test_customers_go_to_their_own_pages_and_admins_can_preview(): void
    {
        $service = $this->service();
        $this->actingAs($this->customer())->get(Seo::serviceUrl($service))->assertRedirect(route('customer.services.show', $service->id));
        $this->get(route('public.catalog'))->assertRedirect(route('customer.services.index'));
        $this->actingAs($this->admin())->get(Seo::serviceUrl($service))->assertOk()->assertSee(route('admin.dashboard'), false);
    }

    public function test_robots_and_sitemap(): void
    {
        $this->settings(['maintenance_enabled' => '1']); // stays readable during maintenance
        $robots = $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin')->assertSee('Sitemap: ' . route('sitemap'));
        $this->assertSame([], $robots->headers->getCookies());

        $hidden = Service::where('slug', 'haafedk-test-2')->firstOrFail();
        $hidden->update(['status' => 'inactive']);
        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertDontSee(Seo::serviceUrl($hidden), false);
        $this->assertSame([], $xml->headers->getCookies());
        $doc = simplexml_load_string($xml->getContent());
        $locs = array_map(fn ($u) => (string) $u->loc, iterator_to_array($doc->url, false));
        foreach ([route('home'), route('ar.home'), Seo::serviceUrl($this->service(), 'en'), Seo::serviceUrl($this->service(), 'ar')] as $url) {
            $this->assertContains($url, $locs);
        }
        $this->assertSame($locs, array_values(array_unique($locs)));

        // Every address in the sitemap opens directly and names itself as canonical.
        $this->settings(['maintenance_enabled' => '0']);
        foreach ($locs as $url) {
            app()->setLocale('en'); // one app instance serves every request of a test
            $this->get($url)->assertOk()->assertSee('<link rel="canonical" href="' . $url . '">', false);
        }
    }

    public function test_private_pages_and_the_dev_site_are_not_indexed(): void
    {
        $this->get(route('login'))->assertOk()->assertHeaderMissing('X-Robots-Tag')->assertSee('<link rel="canonical" href="' . route('login') . '">', false);
        $this->get(route('password.request'))->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
        $this->get(route('admin.login'))->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->assertDontSee('rel="canonical"', false);

        $this->get('https://dev.unlock-sd.com/')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('https://dev.unlock-sd.com/robots.txt')->assertOk()->assertDontSee('Sitemap')->assertDontSee('/admin');
        $this->get('https://dev.unlock-sd.com/sitemap.xml')->assertNotFound();
    }

    public function test_home_announcements_do_not_vouch_for_other_sites(): void
    {
        Announcement::create(['text' => 'Outside', 'link' => 'https://other.example/x', 'color' => 'green', 'is_active' => true, 'sort_order' => 1]);
        Announcement::create(['text' => 'Inside', 'link' => url('/terms'), 'color' => 'green', 'is_active' => true, 'sort_order' => 2]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#href="https://other\.example/x"\s+rel="nofollow noopener noreferrer"#', $html);
        $this->assertDoesNotMatchRegularExpression('#href="[^"]*/terms"\s+rel="nofollow#', $html);
    }

    public function test_settings_form_saves_the_search_engine_fields(): void
    {
        $settings = app(SettingRepository::class)->all();
        $this->actingAs($this->admin())->get(route('admin.settings'))->assertOk()->assertSee('name="public_catalog"', false)->assertSee('name="seo_title_ar"', false);
        $this->put(route('admin.settings.update'), array_merge($settings, ['public_catalog' => '0', 'public_prices' => '1', 'seo_title_ar' => 'عنوان']))->assertSessionHasNoErrors();

        $saved = app(SettingRepository::class);
        $this->assertSame('0', $saved->get('public_catalog'));
        $this->assertSame('1', $saved->get('public_prices'));
        $this->assertSame('عنوان', $saved->get('seo_title_ar'));
    }
}
