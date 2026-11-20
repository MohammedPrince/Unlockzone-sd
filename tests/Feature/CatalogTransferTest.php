<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Settings > Export / Import all categories and services. */
class CatalogTransferTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function makeCatalog(): array
    {
        $provider = ApiProvider::forceCreate(['name' => 'Supplier X', 'type' => 'dhru', 'url' => 'https://supplier.example/api', 'username' => 'u', 'api_key' => 'k', 'status' => 'active']);
        $category = ServiceCategory::create(['name' => 'Transfer Cat', 'slug' => 'transfer-cat', 'status' => 'active', 'sort_order' => 3]);
        $imei = $category->fields()->create(['label' => 'IMEI', 'input_type' => 'imei', 'is_required' => true, 'sort_order' => 0]);
        $email = $category->fields()->create(['label' => 'Email', 'input_type' => 'email', 'is_required' => false, 'sort_order' => 1]);

        $manual = Service::create([
            'service_category_id' => $category->id, 'name' => 'Transfer Manual', 'slug' => 'transfer-manual', 'price' => '12.50',
            'status' => 'active', 'delivery_time' => '1-3 Hours', 'is_instant' => true,
            'configuration' => ['field_ids' => [$email->id], 'execution' => 'manual'],
        ]);
        Service::whereKey($manual->id)->update(['vip_price' => '10.00']);

        Service::create([
            'service_category_id' => $category->id, 'name' => 'Transfer Api', 'slug' => 'transfer-api', 'price' => '5.00',
            'status' => 'inactive', 'configuration' => ['field_ids' => [$imei->id], 'execution' => 'api'],
            'api_provider_id' => $provider->id, 'remote_service_id' => '77',
        ]);

        $json = $this->actingAs($this->admin())->get(route('admin.settings.catalog.export'))->assertOk()->streamedContent();

        return json_decode($json, true);
    }

    private function importFile(array $data, bool $update = true)
    {
        return $this->actingAs($this->admin())->post(route('admin.settings.catalog.import'), [
            'catalog_file' => UploadedFile::fake()->createWithContent('catalog.json', json_encode($data)),
            'update_existing' => $update ? '1' : '0',
        ]);
    }

    public function test_export_then_import_into_an_empty_site_recreates_everything(): void
    {
        $data = $this->makeCatalog();
        $this->assertSame('dhru-server-catalog', $data['format']);

        // Simulate the other site: no services, categories or providers, but a provider with the same URL.
        Service::query()->forceDelete();
        ServiceCategory::query()->forceDelete();
        ApiProvider::query()->delete();
        $prodProvider = ApiProvider::forceCreate(['name' => 'Renamed', 'type' => 'dhru', 'url' => 'http://www.supplier.example/api/', 'username' => 'u', 'api_key' => 'k', 'status' => 'active']);

        $this->importFile($data)->assertRedirect(route('admin.services.index'))->assertSessionHasNoErrors();

        $category = ServiceCategory::where('name', 'Transfer Cat')->firstOrFail();
        $this->assertSame(3, (int) $category->sort_order);
        $this->assertSame(['imei', 'email'], $category->fields()->pluck('input_type')->all());

        $manual = Service::where('name', 'Transfer Manual')->firstOrFail();
        $this->assertSame('12.50', $manual->price);
        $this->assertSame('10.00', $manual->vip_price);
        $this->assertTrue($manual->is_instant);
        $this->assertSame(['Email'], $manual->inputFields()->pluck('label')->all());

        $api = Service::where('name', 'Transfer Api')->firstOrFail();
        $this->assertSame('inactive', $api->status);
        $this->assertSame($prodProvider->id, $api->api_provider_id);
        $this->assertSame('77', $api->remote_service_id);
        $this->assertSame(['IMEI'], $api->inputFields()->pluck('label')->all());
    }

    public function test_import_updates_existing_by_name_or_leaves_them_alone(): void
    {
        $data = $this->makeCatalog();
        $data['categories'][array_search('Transfer Cat', array_column($data['categories'], 'name'))]['services'][1]['price'] = '99.00';
        $count = Service::count();

        $this->importFile($data, update: false)->assertSessionHasNoErrors();
        $this->assertSame('12.50', Service::where('name', 'Transfer Manual')->value('price'));
        $this->assertSame($count, Service::count());

        $this->importFile($data)->assertSessionHasNoErrors();
        $this->assertSame('99.00', Service::where('name', 'Transfer Manual')->value('price'));
        $this->assertSame($count, Service::count());
        $this->assertSame(1, ServiceCategory::where('name', 'Transfer Cat')->count());
    }

    public function test_missing_provider_saves_the_service_as_manual(): void
    {
        $data = $this->makeCatalog();
        Service::query()->forceDelete();
        ApiProvider::query()->delete();

        $this->importFile($data)->assertSessionHasNoErrors();

        $api = Service::where('name', 'Transfer Api')->firstOrFail();
        $this->assertNull($api->api_provider_id);
        $this->assertSame('manual', $api->configuration['execution']);
    }

    public function test_a_wrong_file_is_refused(): void
    {
        $this->importFile(['hello' => 'world'])->assertSessionHasErrors('catalog_file');
    }
}
