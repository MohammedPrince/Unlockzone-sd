<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Importing from a provider's price list: select all, categories from the provider's groups, no duplicates. */
class ProviderImportTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    /** A classic DHRU provider whose list has two groups, a service named like an existing one, and a name listed twice. */
    private function syncedProvider(): ApiProvider
    {
        Http::fake(fn ($r) => match ($r['action']) {
            'accountinfo' => Http::response(['SUCCESS' => [['AccoutInfo' => ['credit' => '957.03', 'currency' => 'USD']]]]),
            'imeiservicelist' => Http::response(['SUCCESS' => [['LIST' => [
                'Infinity Activation CM2' => ['GROUPNAME' => 'Infinity Activation CM2', 'GROUPTYPE' => 'SERVER', 'SERVICES' => [
                    '1526' => ['SERVICEID' => 1526, 'SERVICENAME' => 'Infinity BB5 Active For Cm2', 'CREDIT' => '42.09', 'TIME' => 'Instant',
                        'Requires.Custom' => [['fieldname' => 'SN', 'required' => '1']]],
                    '1562' => ['SERVICEID' => 1562, 'SERVICENAME' => 'Infinity Box/Dongle Credits Any Quantity', 'CREDIT' => '0.6', 'TIME' => 'Instant',
                        'Requires.Custom' => [['fieldname' => 'Username', 'required' => '1']]],
                ]],
                'Honor FRP' => ['GROUPNAME' => 'Honor FRP', 'GROUPTYPE' => 'IMEI', 'SERVICES' => [
                    '900' => ['SERVICEID' => 900, 'SERVICENAME' => 'Honor Frp Key by Sn Only Instant', 'CREDIT' => '40', 'TIME' => 'Instant'],
                    '901' => ['SERVICEID' => 901, 'SERVICENAME' => 'HONOR FRP KEY BY SN ONLY INSTANT', 'CREDIT' => '41', 'TIME' => 'Instant'],
                ]],
            ]]]]),
        });

        $this->actingAs($this->admin())->post(route('admin.providers.store'), [
            'name' => 'Server Service', 'url' => 'https://provider.test', 'username' => 'me', 'api_key' => 'K', 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $provider = ApiProvider::sole();
        $this->post(route('admin.providers.sync', $provider->id))->assertSessionHasNoErrors();

        return $provider;
    }

    public function test_page_has_select_all_and_live_search(): void
    {
        $provider = $this->syncedProvider();

        $this->get(route('admin.providers.services', $provider->id))->assertOk()
            ->assertSee('data-check-all', false)
            ->assertSee('data-live-search="#provider-results"', false)
            ->assertSee('Import all 4')
            ->assertSee('Provider&#039;s group (category created if missing)', false);

        // Live search swaps this block: searching narrows it and the "Import all" count follows.
        $this->get(route('admin.providers.services', [$provider->id, 'q' => 'Infinity']))->assertOk()->assertSee('Import all 2');
    }

    public function test_import_all_matching_into_categories_named_after_the_provider_groups(): void
    {
        $provider = $this->syncedProvider();
        // Existing category, different case: reused, not created again.
        ServiceCategory::create(['name' => 'honor frp', 'slug' => 'honor-frp-existing', 'status' => 'active', 'sort_order' => 0]);

        $this->post(route('admin.providers.import', $provider->id), [
            'scope' => 'all', 'q' => '', 'type' => '', 'service_category_id' => '', 'margin' => 10, 'status' => 'active',
        ])->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, '3 services imported') && str_contains($m, '1 new categories'));

        $infinity = ServiceCategory::where('name', 'Infinity Activation CM2')->sole();
        $this->assertSame('active', $infinity->status);
        $this->assertSame(2, $infinity->services()->count());
        $this->assertSame(1, ServiceCategory::where('slug', 'honor-frp-existing')->sole()->services()->count());
        $this->assertSame(1, ServiceCategory::where('name', 'like', 'honor frp')->count());
        $this->assertSame('46.30', Service::where('remote_service_id', '1526')->sole()->price); // 42.09 + 10%

        // The provider lists "Honor Frp Key by Sn Only Instant" twice: only one service on the site.
        $this->assertSame(1, Service::where('name', 'like', 'honor frp key by sn only instant')->count());

        // Again: nothing new.
        $this->post(route('admin.providers.import', $provider->id), [
            'scope' => 'all', 'service_category_id' => '', 'margin' => 10, 'status' => 'active',
        ])->assertSessionHas('success', fn ($m) => str_contains($m, '0 services imported'));
        $this->assertSame(3, Service::whereNotNull('remote_service_id')->count());
    }

    public function test_linked_services_count_opens_the_services_list_for_that_provider(): void
    {
        $provider = $this->syncedProvider();
        $this->post(route('admin.providers.import', $provider->id), [
            'scope' => 'selected', 'remote_ids' => ['1526', '1562'], 'service_category_id' => '', 'margin' => 0, 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $manual = Service::where('remote_service_id', null)->firstOrFail();

        $link = route('admin.services.index', ['provider' => $provider->id]);
        $this->get(route('admin.providers.index'))->assertOk()->assertSee('href="' . $link . '"', false);

        // Only this provider's services, with the bulk "Move selected to" bar to change their category.
        $this->get($link)->assertOk()
            ->assertSee('Infinity BB5 Active For Cm2')
            ->assertSee('Infinity Box/Dongle Credits Any Quantity')
            ->assertDontSee($manual->name)
            ->assertSee('id="bulk-move"', false);
    }

    public function test_import_all_respects_the_search_and_a_chosen_category(): void
    {
        $provider = $this->syncedProvider();
        $category = ServiceCategory::where('slug', 'server-services')->sole();

        $this->post(route('admin.providers.import', $provider->id), [
            'scope' => 'all', 'q' => 'Infinity', 'service_category_id' => $category->id, 'margin' => 0, 'status' => 'inactive',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $category->services()->where('status', 'inactive')->count());
        $this->assertFalse(ServiceCategory::where('name', 'Infinity Activation CM2')->exists());
        $this->assertSame(0, Service::where('remote_service_id', '900')->count());
    }

    public function test_an_existing_service_with_the_same_name_is_switched_to_the_api_not_duplicated(): void
    {
        $provider = $this->syncedProvider();
        // Typed in by hand (or imported from a text file) earlier, with its own category and status.
        $mine = ServiceCategory::create(['name' => 'IMEI Services', 'slug' => 'imei-services', 'status' => 'active', 'sort_order' => 0]);
        $manual = Service::create([
            'service_category_id' => $mine->id, 'name' => 'Infinity BB5  active for CM2', 'slug' => 'manual-bb5',
            'price' => '50.00', 'status' => 'inactive', 'description' => 'My notes', 'configuration' => ['field_ids' => [], 'execution' => 'manual'],
        ]);

        $this->post(route('admin.providers.import', $provider->id), [
            'scope' => 'selected', 'remote_ids' => ['1526', '1562'], 'service_category_id' => '', 'margin' => 0, 'status' => 'active',
        ])->assertSessionHas('success', fn ($m) => str_contains($m, '1 services imported') && str_contains($m, '1 existing services'));

        $manual->refresh();
        $this->assertSame(1, Service::where('name', 'like', 'infinity bb5%')->count());
        $this->assertSame((int) $provider->id, (int) $manual->api_provider_id);
        $this->assertSame('1526', $manual->remote_service_id);
        $this->assertSame('42.09', $manual->price);
        $this->assertTrue($manual->usesProvider());
        // Kept: its category, status and description; its inputs now come from the provider.
        $this->assertSame($mine->id, $manual->service_category_id);
        $this->assertSame('inactive', $manual->status);
        $this->assertSame('My notes', $manual->description);
        $this->assertSame(['SN'], $manual->inputFields()->pluck('label')->all());

        // The list now shows it as imported.
        $this->get(route('admin.providers.services', $provider->id))->assertSee(route('admin.services.edit', $manual->id));
    }

    public function test_nothing_ticked_is_refused(): void
    {
        $provider = $this->syncedProvider();

        $this->post(route('admin.providers.import', $provider->id), ['scope' => 'selected', 'margin' => 0, 'status' => 'active'])
            ->assertSessionHasErrors('remote_ids');
        $this->assertSame(0, Service::whereNotNull('remote_service_id')->count());
    }
}
