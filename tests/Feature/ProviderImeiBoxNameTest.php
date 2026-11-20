<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\CustomerWallet;
use App\Models\Service;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DHRU Fusion IMEI services can rename their IMEI box (CUSTOM.customname): "Lock Code" for Mi account removal,
 * "Email" for iCloud. The customer is asked for that, and it is sent to the provider as the IMEI.
 */
class ProviderImeiBoxNameTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_renamed_imei_box_is_asked_for_and_sent_as_the_imei(): void
    {
        // Before the fix the box name was ignored: start like the live site, with Mi asking for a plain IMEI.
        $renamed = false;
        Http::fake(function ($r) use (&$renamed) {
            $mi = ['SERVICEID' => 30092, 'SERVICENAME' => 'XIAOMI MI ACCOUNT REMOVE (EUROPE)', 'CREDIT' => '5.1', 'TIME' => 'Instant'];
            if ($renamed) {
                $mi['CUSTOM'] = ['allow' => '1', 'bulk' => '1', 'customname' => 'Lock Code', 'custominfo' => 'You can order max. 1 of IMEI service', 'customlen' => '0'];
            }

            return match ($r['action']) {
                'accountinfo' => Http::response(['SUCCESS' => [['AccoutInfo' => ['credit' => '10', 'currency' => 'USD']]]]),
                'imeiservicelist' => Http::response(['SUCCESS' => [['LIST' => [
                    'Xiaomi' => ['GROUPNAME' => 'Xiaomi', 'GROUPTYPE' => 'IMEI', 'SERVICES' => [
                        '30092' => $mi,
                        '40001' => ['SERVICEID' => 40001, 'SERVICENAME' => 'Haafedk iCloud Premium 1 Year A12 - A13', 'CREDIT' => '25', 'TIME' => '1 Day',
                            'CUSTOM' => ['allow' => '1', 'customname' => 'Email']],
                        '50001' => ['SERVICEID' => 50001, 'SERVICENAME' => 'Plain IMEI check', 'CREDIT' => '1', 'TIME' => 'Instant',
                            'CUSTOM' => ['allow' => '0', 'customname' => '']],
                    ]],
                ]]]]),
                'placeimeiorder' => Http::response(['SUCCESS' => [['MESSAGE' => 'Order received', 'REFERENCEID' => '777']]]),
            };
        });

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.providers.store'), [
            'name' => 'AmrService', 'url' => 'https://provider.test', 'username' => 'me', 'api_key' => 'K', 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $provider = ApiProvider::sole();
        $this->post(route('admin.providers.sync', $provider->id))->assertSessionHasNoErrors();
        $this->post(route('admin.providers.import', $provider->id), ['scope' => 'all', 'service_category_id' => '', 'margin' => 0, 'status' => 'active'])
            ->assertSessionHasNoErrors();

        $mi = Service::where('remote_service_id', '30092')->sole();
        $icloud = Service::where('remote_service_id', '40001')->sole();
        $plain = Service::where('remote_service_id', '50001')->sole();
        $this->assertSame(['IMEI'], $mi->inputFields()->pluck('label')->all());
        $this->assertSame(['Email'], $icloud->inputFields()->pluck('label')->all());
        $this->assertSame('email', $icloud->inputFields()->first()->input_type);
        $this->assertSame(['IMEI'], $plain->inputFields()->pluck('label')->all());

        // The provider's list now names Mi's box "Lock Code": the button on the service's edit page updates it.
        $renamed = true;
        $this->get(route('admin.services.edit', $mi->id))->assertOk()->assertSee('Update required inputs from the API');
        $this->post(route('admin.services.provider-fields', $mi->id))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Required inputs updated from the API') && str_contains($m, 'Customers are asked for: Lock Code'));
        $this->assertSame(['Lock Code'], $mi->fresh()->inputFields()->pluck('label')->all());
        $this->post(route('admin.services.provider-fields', $mi->id))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'already match'));

        // A customer orders with a lock code (not 15 digits): it goes to the provider as the IMEI.
        $customer = new User(['first_name' => 'C', 'name' => 'C', 'username' => 'cust', 'email' => 'c@example.com', 'password' => 'secret-pass-1']);
        $customer->role = 'customer';
        $customer->status = 'active';
        $customer->save();
        CustomerWallet::create(['user_id' => $customer->id, 'balance' => 0]);
        app(CreditService::class)->recharge($customer, 50, null, 'test', $admin->id);

        $lock = $mi->fresh()->inputFields()->first();
        $this->actingAs($customer)->get(route('customer.services.show', $mi->id))->assertOk()->assertSee('Lock Code')->assertDontSee('15 digits, e.g.');
        $this->actingAs($customer)->post(route('customer.services.order', $mi->id), [
            'idempotency_key' => (string) Str::uuid(), 'fields' => [$lock->id => 'LC-98765'], 'terms' => '1',
        ])->assertSessionHasNoErrors();

        Http::assertSent(fn ($r) => $r['action'] === 'placeimeiorder'
            && str_contains($r['parameters'], '<ID>30092</ID>') && str_contains($r['parameters'], '<IMEI>LC-98765</IMEI>')
            && !str_contains($r['parameters'], 'CUSTOMFIELD'));
    }

    public function test_one_button_updates_the_inputs_of_all_api_services_from_every_provider(): void
    {
        $renamed = false;
        Http::fake(function ($r) use (&$renamed) {
            if (str_contains((string) $r->url(), 'down.test')) {
                return Http::response('Service Unavailable', 503);
            }
            $custom = $renamed ? ['allow' => '1', 'customname' => 'Lock Code'] : ['allow' => '0'];

            return match ($r['action']) {
                'accountinfo' => Http::response(['SUCCESS' => [['AccoutInfo' => ['credit' => '10', 'currency' => 'USD']]]]),
                'imeiservicelist' => Http::response(['SUCCESS' => [['LIST' => [
                    'Xiaomi' => ['GROUPNAME' => 'Xiaomi', 'GROUPTYPE' => 'IMEI', 'SERVICES' => [
                        '1' => ['SERVICEID' => 1, 'SERVICENAME' => 'Mi Europe', 'CREDIT' => '5', 'CUSTOM' => $custom],
                        '2' => ['SERVICEID' => 2, 'SERVICENAME' => 'Mi Global', 'CREDIT' => '6', 'CUSTOM' => $custom],
                    ]],
                ]]]]),
            };
        });

        $this->actingAs($this->admin())->post(route('admin.providers.store'), [
            'name' => 'AmrService', 'url' => 'https://provider.test', 'username' => 'me', 'api_key' => 'K', 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $provider = ApiProvider::sole();
        $this->post(route('admin.providers.sync', $provider->id));
        $this->post(route('admin.providers.import', $provider->id), ['scope' => 'all', 'service_category_id' => '', 'margin' => 0, 'status' => 'active']);
        $this->assertSame(['IMEI'], Service::where('remote_service_id', '1')->sole()->inputFields()->pluck('label')->all());

        // A second provider that is down: reported, the others still updated.
        $down = ApiProvider::forceCreate(['name' => 'Down Ltd', 'type' => 'dhru', 'url' => 'https://down.test', 'username' => 'u', 'api_key' => 'k', 'status' => 'active']);
        Service::where('remote_service_id', '2')->sole()->replicate()->fill(['slug' => 'down-copy', 'name' => 'Down copy'])
            ->forceFill(['api_provider_id' => $down->id, 'remote_service_id' => '9'])->save();

        $renamed = true;
        $this->get(route('admin.services.index'))->assertOk()->assertSee('Update all inputs from the API');
        $this->get(route('admin.providers.index'))->assertOk()->assertSee('Update all inputs from the API');
        $this->post(route('admin.providers.update-fields'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 provider checked') && str_contains($m, '2 services got their required inputs updated from the API'))
            ->assertSessionHasErrors('provider');

        foreach (['1', '2'] as $id) {
            $this->assertSame(['Lock Code'], Service::where('api_provider_id', $provider->id)->where('remote_service_id', $id)->sole()->inputFields()->pluck('label')->all());
        }
    }

    public function test_a_service_not_linked_to_a_provider_has_no_update_button(): void
    {
        $service = Service::where('slug', 'haafedk-test-1')->firstOrFail();

        $this->actingAs($this->admin())->get(route('admin.services.edit', $service->id))->assertOk()->assertDontSee('Update required inputs from the API');
        $this->post(route('admin.services.provider-fields', $service->id))->assertNotFound();
    }
}
