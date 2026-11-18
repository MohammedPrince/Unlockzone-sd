<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\CustomerWallet;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\CreditService;
use App\Services\ProviderOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Quantity pricing, live order status, live search and the DHRU Fusion Pro Reseller API. */
class QuantityLiveAndResellerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function tearDown(): void
    {
        ProviderOrderService::$backgroundSyncInTests = false;
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function customer(float $balance = 50, string $username = 'ali'): User
    {
        $user = new User(['first_name' => 'Ali', 'name' => 'Ali', 'username' => $username, 'email' => "{$username}@example.com", 'password' => 'secret-pass-1']);
        $user->role = 'customer';
        $user->status = 'active';
        $user->save();
        CustomerWallet::create(['user_id' => $user->id, 'balance' => 0]);
        app(CreditService::class)->recharge($user, $balance, null, 'test', $this->admin()->id);

        return $user;
    }

    private function balance(User $user): string
    {
        return $user->wallet()->first()->balance;
    }

    /** A 0.92 service that asks for a username and a QTY. */
    private function quantityService(): Service
    {
        $category = ServiceCategory::create(['name' => 'Credits', 'slug' => 'credits', 'status' => 'active', 'sort_order' => 0]);
        $user = $category->fields()->create(['label' => 'Username', 'input_type' => 'text', 'is_required' => true, 'sort_order' => 0]);
        $qty = $category->fields()->create(['label' => 'QTY', 'input_type' => 'number', 'is_required' => true, 'sort_order' => 1]);

        return Service::create([
            'service_category_id' => $category->id, 'name' => 'Tool Credits', 'slug' => 'tool-credits',
            'price' => '0.92', 'status' => 'active', 'configuration' => ['field_ids' => [$user->id, $qty->id]],
        ]);
    }

    private function order(User $customer, Service $service, array $fields)
    {
        return $this->actingAs($customer)->post(route('customer.services.order', $service->id), [
            'idempotency_key' => (string) Str::uuid(), 'fields' => $fields, 'terms' => '1',
        ]);
    }

    public function test_a_qty_field_multiplies_the_price(): void
    {
        $customer = $this->customer(50);
        $service = $this->quantityService();
        [$userField, $qtyField] = $service->fieldIds();

        $this->actingAs($customer)->get(route('customer.services.show', $service->id))->assertOk()
            ->assertSee('data-qty-form', false)->assertSee('Total');

        $this->order($customer, $service, [$userField => 'bob', $qtyField => '0'])->assertSessionHasErrors("fields.{$qtyField}");
        $this->order($customer, $service, [$userField => 'bob', $qtyField => '2.5'])->assertSessionHasErrors("fields.{$qtyField}");
        // 100 x 0.92 = 92 is more than the balance.
        $this->order($customer, $service, [$userField => 'bob', $qtyField => '100'])->assertSessionHasErrors();
        $this->assertSame(0, ServiceRequest::count());

        $this->order($customer, $service, [$userField => 'bob', $qtyField => '5'])->assertSessionHasNoErrors();
        $request = ServiceRequest::sole();
        $this->assertSame('4.60', $request->amount);
        $this->assertSame('45.40', $this->balance($customer));
        $this->assertSame(5, \App\Services\ServiceRequestService::quantityOf($request->request_data));

        // A failed order refunds the full total.
        app(\App\Services\ServiceRequestService::class)->updateStatus($request->id, 'failed', null, 'No', $this->admin());
        $this->assertSame('50.00', $this->balance($customer));
    }

    public function test_live_status_endpoints_only_show_your_own_orders(): void
    {
        $alice = $this->customer(50, 'alice');
        $bob = $this->customer(50, 'bob');
        $service = Service::where('slug', 'haafedk-test-1')->firstOrFail();
        $this->order($alice, $service, [$service->fieldIds()[0] => '123456789012345']);
        $order = ServiceRequest::sole();

        $this->actingAs($alice)->getJson(route('customer.requests.live', ['ids' => [$order->id]]))->assertOk()
            ->assertJsonPath("orders.{$order->id}.status", 'processing');
        $this->actingAs($bob)->getJson(route('customer.requests.live', ['ids' => [$order->id]]))->assertOk()
            ->assertJsonMissingPath("orders.{$order->id}");
        $this->actingAs($this->admin())->getJson(route('admin.requests.live', ['ids' => [$order->id]]))->assertOk()
            ->assertJsonPath("orders.{$order->id}.label", 'Processing');

        // The order pages mark open orders for the poller.
        $this->actingAs($alice)->get(route('customer.requests.index'))->assertSee('data-live-order="' . $order->id . '"', false);
        $this->actingAs($this->admin())->get(route('admin.requests.show', $order->id))->assertSee('data-live-order="' . $order->id . '"', false);
    }

    public function test_open_provider_orders_are_checked_in_the_background_without_cron(): void
    {
        $customer = $this->customer();
        $provider = new ApiProvider(['name' => 'GSM', 'type' => 'dhru', 'url' => 'https://provider.test', 'username' => 'me', 'status' => 'active']);
        $provider->api_key = 'KEY';
        $provider->save();
        $service = Service::where('slug', 'haafedk-test-1')->firstOrFail();
        $service->update(['api_provider_id' => $provider->id, 'remote_service_id' => '134']);

        Http::fake(fn ($r) => match ($r['action']) {
            'placeimeiorder' => Http::response(['SUCCESS' => [['REFERENCEID' => '9001']]]),
            'getimeiorder' => Http::response(['SUCCESS' => [['STATUS' => '4', 'CODE' => 'DONE-42']]]),
        });

        $this->order($customer, $service, [$service->fieldIds()[0] => '123456789012345']);
        $order = ServiceRequest::sole();
        $this->assertSame('processing', $order->status);

        // Polling the live endpoint runs the check after the answer is sent (at most once a minute).
        ProviderOrderService::$backgroundSyncInTests = true;
        Cache::forget('providers:sync-tick');
        $this->actingAs($customer)->getJson(route('customer.requests.live', ['ids' => [$order->id]]))->assertOk();

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('DONE-42', $order->fresh()->response_data['result']);
    }

    public function test_services_search_has_live_results(): void
    {
        $this->actingAs($this->admin())->get(route('admin.services.index', ['q' => 'Haafedk']))->assertOk()
            ->assertSee('data-live-search="#services-results"', false)
            ->assertSee('id="services-results" data-live-results', false);
    }

    public function test_dhru_fusion_pro_reseller_api_connect_import_order_and_status(): void
    {
        $admin = $this->admin();
        $status = 'in-process';

        Http::fake(function ($request) use (&$status) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $path === '/api/reseller/v1/account' => Http::response(['status' => 'success', 'code' => 200, 'data' => [
                    'email' => 'me@x.com', 'wallet' => ['balance' => '120.50', 'currency_code' => 'USD'],
                ]]),
                $path === '/api/reseller/v1/products' => Http::response(['status' => 'success', 'code' => 200, 'data' => [
                    'categories' => [['category_id' => 7, 'category_name' => 'iCloud']],
                    'products' => [
                        '501' => ['product_id' => 501, 'product_uuid' => 'u-501', 'name' => 'iPhone Check', 'price' => '1.25', 'category_id' => 7,
                            'delivery_time' => 'Instant', 'fields' => [['name' => 'IMEI', 'required' => true]]],
                        '502' => ['product_id' => 502, 'name' => 'Tool Credits', 'price' => 0.5, 'category_id' => 7,
                            'fields' => [['name' => 'Username', 'required' => true], ['name' => 'Quantity']]],
                    ],
                ]]),
                $path === '/api/reseller/v1/order' && $request->method() === 'POST' => Http::response(['status' => 'success', 'message' => '1 Order submitted', 'code' => 200,
                    'data' => [[['order_uuid' => 'D2504', 'amount' => 1.25, 'currency_code' => 'USD', 'reference_id' => 'x']]]]),
                $path === '/api/reseller/v1/order' => Http::response(['status' => 'success', 'code' => 200, 'data' => [
                    'quantity' => 1, 'replay' => $status === 'success' ? 'Model: iPhone 11' : '', 'status' => $status,
                ]]),
                default => Http::response(['status' => 'error', 'code' => 404], 404),
            };
        });

        // Connect: token only, no username.
        $this->actingAs($admin)->post(route('admin.providers.store'), [
            'name' => 'Pro Workspace', 'type' => 'dhru_reseller', 'url' => 'https://ws.example.com', 'api_key' => 'TOKEN-abc', 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $provider = ApiProvider::sole();
        $this->assertSame('dhru_reseller', $provider->type);
        $this->assertSame('120.50', $provider->balance);
        $this->assertSame('USD', $provider->currency);
        Http::assertSent(fn ($r) => $r->url() === 'https://ws.example.com/api/reseller/v1/account' && $r->hasHeader('Authorization', 'Bearer TOKEN-abc'));

        // Price list: IMEI goes to the IMEI box, "Quantity" is handled by the system.
        $this->actingAs($admin)->post(route('admin.providers.sync', $provider->id))->assertSessionHasNoErrors();
        $this->assertSame(2, $provider->remoteServices()->count());
        $check = $provider->remoteServices()->where('remote_id', '501')->sole();
        $this->assertSame('iCloud', $check->group_name);
        $this->assertTrue($check->needs_imei);
        $this->assertSame(['Username'], collect($provider->remoteServices()->where('remote_id', '502')->sole()->fields)->pluck('name')->all());

        $category = ServiceCategory::where('slug', 'server-services')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.providers.import', $provider->id), [
            'remote_ids' => ['501'], 'service_category_id' => $category->id, 'margin' => 0, 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $service = Service::where('remote_service_id', '501')->sole();
        $this->assertSame(['IMEI'], $service->inputFields()->pluck('label')->all());

        // Order: JSON body with the product id, the IMEI, our reference and the quantity.
        $customer = $this->customer();
        $this->order($customer, $service, [$service->fieldIds()[0] => '352906110008721'])->assertSessionHasNoErrors();
        $order = ServiceRequest::sole();
        $this->assertSame('D2504', $order->remote_order_id);
        Http::assertSent(function ($r) use ($order) {
            if ($r->method() !== 'POST' || !str_ends_with($r->url(), '/api/reseller/v1/order')) {
                return false;
            }
            $body = json_decode($r->body(), true);

            return $body[0]['product_id'] === 501
                && $body[0]['fields'][0]['IMEI'] === '352906110008721'
                && $body[0]['fields'][0]['reference_id'] === $order->reference
                && $body[0]['fields'][0]['Quantity'] === 1
                && $r->hasHeader('X-Dhru-Feedback-Mode', 'polling');
        });

        // Still working at the provider, then done.
        app(ProviderOrderService::class)->syncOpen();
        $this->assertSame('processing', $order->fresh()->status);

        $status = 'success';
        // The provider's notice only triggers a check; the result is read from the API.
        $this->postJson(route('providers.callback', $provider->id), ['order_id' => 'D2504', 'status' => 'success', 'replay' => base64_encode('FAKE')])->assertOk();
        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('Model: iPhone 11', $order->response_data['result']);
    }

    public function test_reseller_api_errors_in_a_2xx_answer_are_errors(): void
    {
        $provider = new ApiProvider(['name' => 'Pro', 'type' => 'dhru_reseller', 'url' => 'https://ws.example.com', 'username' => '', 'status' => 'active']);
        $provider->api_key = 'T';
        $provider->save();

        Http::fake(['*' => Http::response(['status' => 'error', 'code' => 422, 'message' => 'Invalid IMEI'], 201)]);

        $this->expectException(\App\Exceptions\ProviderException::class);
        $this->expectExceptionMessage('Invalid IMEI');
        $provider->client()->placeOrder('501', '1', [], false, 1, 'SR-1');
    }
}
