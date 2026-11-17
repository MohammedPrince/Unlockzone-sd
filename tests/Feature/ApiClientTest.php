<?php

namespace Tests\Feature;

use App\Exceptions\ProviderException;
use App\Models\ApiClient;
use App\Models\ApiProvider;
use App\Models\CustomerWallet;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\CreditService;
use App\Services\ServiceRequestService;
use App\Support\DhruFusionClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** This server as an API provider: other servers order our services with a key we give them. */
class ApiClientTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function customer(string $username = 'reseller', float $balance = 50): User
    {
        $user = new User([
            'first_name' => 'Other', 'last_name' => 'Server', 'name' => 'Other Server',
            'username' => $username, 'email' => "{$username}@example.com", 'password' => 'secret-pass-1',
        ]);
        $user->role = 'customer';
        $user->status = 'active';
        $user->save();
        CustomerWallet::create(['user_id' => $user->id, 'balance' => 0]);
        app(CreditService::class)->recharge($user, $balance, null, 'test', $this->admin()->id);

        return $user;
    }

    private function service(string $slug = 'haafedk-test-1'): Service
    {
        return Service::where('slug', $slug)->firstOrFail(); // test-1: 5.00, needs an IMEI
    }

    /** Creates API access through the admin form and returns [client, key]. */
    private function client(User $customer, array $data = []): array
    {
        $this->actingAs($this->admin())->post(route('admin.api-clients.store'), $data + [
            'user_id' => $customer->id, 'access' => 'all', 'status' => 'active',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.providers.index'));

        return [ApiClient::where('user_id', $customer->id)->firstOrFail(), session('new_api_key')['key']];
    }

    /** Our own provider client, with its HTTP calls answered by this application's API. */
    private function remote(string $username, string $key): DhruFusionClient
    {
        Http::fake(function ($request) {
            parse_str($request->body(), $form);

            return Http::response($this->post('/api/index.php', $form)->getContent());
        });

        $provider = new ApiProvider(['name' => 'Us', 'type' => 'dhru', 'url' => 'https://us.test', 'username' => $username, 'status' => 'active']);
        $provider->api_key = $key;

        return new DhruFusionClient($provider);
    }

    private function balance(User $user): string
    {
        return $user->wallet()->first()->balance;
    }

    public function test_another_server_can_sign_in_list_order_and_get_the_result(): void
    {
        $customer = $this->customer();
        [$client, $key] = $this->client($customer);
        $this->assertSame(ApiClient::hashKey($key), $client->api_key_hash);

        $api = $this->remote('reseller', $key);

        $this->assertSame(50.0, $api->accountInfo()['balance']);

        $list = collect($api->serviceList());
        $service = $this->service();
        $row = $list->firstWhere('remote_id', (string) $service->id);
        $this->assertNotNull($row);
        $this->assertSame('IMEI', $row['type']);
        $this->assertSame(5.0, $row['price']);

        $orderId = $api->placeOrder((string) $service->id, '123456789012345');
        $request = ServiceRequest::findOrFail((int) $orderId);
        $this->assertSame($customer->id, $request->user_id);
        $this->assertSame($client->id, $request->api_client_id);
        $this->assertSame('45.00', $this->balance($customer));
        $this->assertSame('pending', $api->orderStatus($orderId)['state']);

        app(ServiceRequestService::class)->updateStatus($request->id, 'completed', "CODE-777\nline 2", null, $this->admin());
        $status = $api->orderStatus($orderId);
        $this->assertSame('done', $status['state']);
        $this->assertSame("CODE-777\nline 2", $status['result']);

        $this->assertNotNull($client->fresh()->last_used_at);
    }

    public function test_a_rejected_order_is_refunded_and_reported_as_rejected(): void
    {
        $customer = $this->customer();
        [, $key] = $this->client($customer);
        $api = $this->remote('reseller', $key);

        $orderId = $api->placeOrder((string) $this->service()->id, '123456789012345');
        app(ServiceRequestService::class)->updateStatus((int) $orderId, 'failed', null, 'Not found in database', $this->admin());

        $status = $api->orderStatus($orderId);
        $this->assertSame('rejected', $status['state']);
        $this->assertSame('Not found in database', $status['result']);
        $this->assertSame('50.00', $this->balance($customer));
    }

    public function test_bad_input_and_low_credit_are_refused_without_charging(): void
    {
        $customer = $this->customer(balance: 3);
        [, $key] = $this->client($customer);
        $api = $this->remote('reseller', $key);

        try {
            $api->placeOrder((string) $this->service()->id, '123');
            $this->fail('A short IMEI was accepted.');
        } catch (ProviderException $e) {
            $this->assertStringContainsString('15', $e->getMessage());
        }

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Insufficient credit');
        try {
            $api->placeOrder((string) $this->service()->id, '123456789012345');
        } finally {
            $this->assertSame(0, ServiceRequest::count());
            $this->assertSame('3.00', $this->balance($customer));
        }
    }

    public function test_only_selected_services_are_shared(): void
    {
        $customer = $this->customer();
        $shared = $this->service('haafedk-test-1');
        $other = $this->service('haafedk-test-2');
        [, $key] = $this->client($customer, ['access' => 'selected', 'service_ids' => [$shared->id]]);
        $api = $this->remote('reseller', $key);

        $ids = collect($api->serviceList())->pluck('remote_id')->all();
        $this->assertSame([(string) $shared->id], $ids);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Invalid Service ID');
        $api->placeOrder((string) $other->id, null, ['Code' => 'X1']);
    }

    public function test_selected_access_needs_at_least_one_service(): void
    {
        $customer = $this->customer();

        $this->actingAs($this->admin())->post(route('admin.api-clients.store'), [
            'user_id' => $customer->id, 'access' => 'selected', 'status' => 'active',
        ])->assertSessionHasErrors('service_ids');
        $this->assertSame(0, ApiClient::count());
    }

    public function test_wrong_key_wrong_username_and_ip_allow_list_are_refused(): void
    {
        $customer = $this->customer();
        [$client, $key] = $this->client($customer);

        $call = fn (array $form) => $this->post('/api/index.php', $form + ['action' => 'accountinfo'])->json('ERROR.0.MESSAGE');

        $this->assertSame('Authentication Failed', $call(['username' => 'reseller', 'apiaccesskey' => 'WRONG']));
        $this->assertSame('Authentication Failed', $call(['username' => 'someone', 'apiaccesskey' => $key]));
        $this->assertNull($call(['username' => 'reseller', 'apiaccesskey' => $key]));
        $this->assertNull($call(['username' => 'reseller@example.com', 'apiaccesskey' => $key]));

        $client->update(['allowed_ips' => '203.0.113.10']);
        $this->assertStringContainsString('not allowed', $call(['username' => 'reseller', 'apiaccesskey' => $key]));
    }

    public function test_admin_can_suspend_resume_renew_key_and_delete_a_client(): void
    {
        $customer = $this->customer();
        [$client, $key] = $this->client($customer);
        $admin = $this->admin();
        $call = fn (string $k) => $this->post('/api/index.php', ['username' => 'reseller', 'apiaccesskey' => $k, 'action' => 'accountinfo'])->json();

        $this->actingAs($admin)->post(route('admin.api-clients.status', $client->id), ['status' => 'suspended'])->assertSessionHasNoErrors();
        $this->assertStringContainsString('suspended', $call($key)['ERROR'][0]['MESSAGE']);

        $this->actingAs($admin)->post(route('admin.api-clients.status', $client->id), ['status' => 'active']);
        $this->assertArrayHasKey('SUCCESS', $call($key));

        $this->actingAs($admin)->post(route('admin.api-clients.key', $client->id));
        $newKey = session('new_api_key')['key'];
        $this->assertNotSame($key, $newKey);
        $this->assertSame('Authentication Failed', $call($key)['ERROR'][0]['MESSAGE']);
        $this->assertArrayHasKey('SUCCESS', $call($newKey));

        $this->actingAs($admin)->delete(route('admin.api-clients.destroy', $client->id))->assertRedirect(route('admin.providers.index'));
        $this->assertSame(0, ApiClient::count());
        $this->assertSame('Authentication Failed', $call($newKey)['ERROR'][0]['MESSAGE']);
        $this->assertNotNull($customer->fresh());
        $this->assertSame('50.00', $this->balance($customer));
    }

    public function test_providers_page_shows_both_sections_and_the_new_key_once(): void
    {
        $customer = $this->customer();
        [$client, $key] = $this->client($customer, ['note' => 'gsm-other.test']);

        // The redirect after creating shows the key.
        $this->actingAs($this->admin())->withSession(['new_api_key' => ['client' => $client->id, 'username' => 'reseller', 'key' => $key]])
            ->get(route('admin.providers.index'))
            ->assertOk()->assertSee('Connect a provider')->assertSee('API clients')->assertSee($key)->assertSee('gsm-other.test');

        // Later visits only show the end of it.
        $this->actingAs($this->admin())->get(route('admin.providers.index'))
            ->assertOk()->assertDontSee($key)->assertSee($client->api_key_hint);

        $this->actingAs($this->admin())->get(route('admin.api-clients.edit', $client->id))->assertOk()->assertSee('Shared services');
        $this->actingAs($this->admin())->get(route('admin.api-clients.create'))->assertOk()->assertDontSee('value="' . $customer->id . '"', false);
    }
}
