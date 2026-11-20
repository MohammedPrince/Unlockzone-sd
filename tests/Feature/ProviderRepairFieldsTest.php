<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\ProviderService;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API providers > "Fix missing inputs": API services get back the inputs their provider asks for. */
class ProviderRepairFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_api_services_that_lost_their_inputs_get_them_back_and_keep_the_admins_own(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $provider = ApiProvider::forceCreate(['name' => 'Supplier X', 'type' => 'dhru', 'url' => 'https://supplier.example/api', 'username' => 'u', 'api_key' => 'k', 'status' => 'active']);
        $category = ServiceCategory::create(['name' => 'IMEI Service', 'slug' => 'imei-service-r', 'status' => 'active', 'sort_order' => 0]);
        $imei = $category->fields()->create(['label' => 'IMEI', 'input_type' => 'imei', 'is_required' => true, 'sort_order' => 0]);
        $note = $category->fields()->create(['label' => 'Note', 'input_type' => 'text', 'is_required' => false, 'sort_order' => 1]);

        // The provider's saved price list: Mi removal needs an IMEI and a lock code; the other only an IMEI.
        ProviderService::create(['api_provider_id' => $provider->id, 'remote_id' => '501', 'name' => 'Mi Account Removal', 'price' => 20, 'needs_imei' => true,
            'fields' => [['name' => 'Lock code', 'type' => 'text', 'required' => true]]]);
        ProviderService::create(['api_provider_id' => $provider->id, 'remote_id' => '502', 'name' => 'Check', 'price' => 1, 'needs_imei' => true, 'fields' => []]);

        // Mi removal lost every input (its IMEI field was removed from the category); the check still has its IMEI
        // plus the admin's own "Note"; a third API service has no saved provider line.
        $mi = Service::create(['service_category_id' => $category->id, 'name' => 'Mi Account Removal', 'slug' => 'mi', 'price' => '23.21', 'status' => 'active',
            'configuration' => ['field_ids' => [], 'execution' => 'api'], 'api_provider_id' => $provider->id, 'remote_service_id' => '501']);
        $check = Service::create(['service_category_id' => $category->id, 'name' => 'Check', 'slug' => 'check', 'price' => '1', 'status' => 'active',
            'configuration' => ['field_ids' => [$imei->id, $note->id], 'execution' => 'api'], 'api_provider_id' => $provider->id, 'remote_service_id' => '502']);
        Service::create(['service_category_id' => $category->id, 'name' => 'Gone', 'slug' => 'gone', 'price' => '1', 'status' => 'active',
            'configuration' => ['field_ids' => [], 'execution' => 'api'], 'api_provider_id' => $provider->id, 'remote_service_id' => '999']);

        $this->actingAs($admin)->get(route('admin.providers.index'))->assertOk()->assertSee('Fix missing inputs');
        $this->post(route('admin.providers.repair-fields'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 API service got its missing inputs back') && str_contains($m, '1 service could not be checked'));

        // Mi removal asks for the IMEI and the lock code again (the lock code field made in the category).
        $lock = $category->fields()->where('label', 'Lock code')->firstOrFail();
        $this->assertSame([$imei->id, $lock->id], $mi->fresh()->fieldIds());
        // Nothing missing on the check: unchanged, the admin's "Note" kept.
        $this->assertSame([$imei->id, $note->id], $check->fresh()->fieldIds());
        $this->assertDatabaseHas('activity_logs', ['action' => 'provider_fields']);

        // The order form asks for them instead of "This service needs no extra information".
        $this->get(route('admin.services.edit', $mi->id))->assertOk();
    }
}
