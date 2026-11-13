<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\ApiProvider;
use App\Models\CustomerWallet;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Repositories\SettingRepository;
use App\Services\CreditService;
use App\Services\ProviderOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Suspensions, favourites, the announcement bar, chat buttons, Arabic, and provider API orders. */
class StorefrontAndProviderTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function customer(float $balance = 50): User
    {
        $user = new User([
            'first_name' => 'Ali', 'last_name' => 'Test', 'name' => 'Ali Test',
            'username' => 'ali', 'email' => 'ali@example.com', 'password' => 'secret-pass-1',
        ]);
        $user->role = 'customer';
        $user->status = 'active';
        $user->save();
        CustomerWallet::create(['user_id' => $user->id, 'balance' => 0]);
        app(CreditService::class)->recharge($user, $balance, null, 'test', $this->admin()->id);

        return $user;
    }

    private function service(): Service
    {
        return Service::where('slug', 'haafedk-test-1')->firstOrFail(); // 5.00, needs an IMEI
    }

    private function order(User $customer, Service $service, array $extra = [])
    {
        return $this->actingAs($customer)->post(route('customer.services.order', $service->id), array_merge([
            'idempotency_key' => (string) Str::uuid(),
            'fields' => [$service->fieldIds()[0] => '123456789012345'],
            'terms' => '1',
        ], $extra));
    }

    private function provider(): ApiProvider
    {
        $provider = new ApiProvider(['name' => 'GSM Free', 'type' => 'dhru', 'url' => 'https://provider.test', 'username' => 'me', 'status' => 'active']);
        $provider->api_key = 'KEY-123';
        $provider->save();

        return $provider;
    }

    private function balance(User $user): string
    {
        return $user->wallet()->first()->balance;
    }

    public function test_terms_must_be_accepted_when_required(): void
    {
        $customer = $this->customer();

        $this->order($customer, $this->service(), ['terms' => null])->assertSessionHasErrors('terms');
        $this->assertSame(0, ServiceRequest::count());

        app(SettingRepository::class)->setMany(['require_terms' => '0']);
        $this->order($customer, $this->service(), ['terms' => null])->assertSessionHasNoErrors();
        $this->assertSame(1, ServiceRequest::count());
    }

    public function test_customer_suspended_from_all_services_or_one_service_cannot_order(): void
    {
        $customer = $this->customer();
        $admin = $this->admin();
        $service = $this->service();
        $other = Service::where('slug', 'haafedk-test-2')->firstOrFail();

        // One service only.
        $this->actingAs($admin)->post(route('admin.customers.services.block', $customer->id), ['service_id' => $service->id])->assertSessionHasNoErrors();
        $this->order($customer, $service)->assertSessionHasErrors('order');
        $this->assertSame(0, ServiceRequest::count());
        $this->assertSame('50.00', $this->balance($customer));
        $this->actingAs($customer)->post(route('customer.services.order', $other->id), [
            'idempotency_key' => (string) Str::uuid(), 'fields' => [$other->fieldIds()[0] => 'CODE1'], 'terms' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, ServiceRequest::count());

        $this->actingAs($admin)->delete(route('admin.customers.services.unblock', [$customer->id, $service->id]));
        $this->order($customer, $service)->assertSessionHasNoErrors();
        $this->assertSame(2, ServiceRequest::count());

        // Every service; the account itself still signs in.
        $this->actingAs($admin)->post(route('admin.customers.services.suspend', $customer->id), ['suspended' => 1]);
        $this->assertTrue($customer->fresh()->services_suspended);
        $this->order($customer, $service)->assertSessionHasErrors('order');
        $this->assertSame(2, ServiceRequest::count());
        $this->actingAs($customer->fresh())->get(route('customer.dashboard'))->assertOk()->assertSee('Ordering services is suspended');

        $this->actingAs($admin)->post(route('admin.customers.services.suspend', $customer->id), ['suspended' => 0]);
        $this->order($customer, $service)->assertSessionHasNoErrors();
    }

    public function test_favourites_new_tag_ticker_and_chat_buttons_show_for_customers(): void
    {
        $customer = $this->customer();
        $service = $this->service();
        Announcement::create(['text' => 'Welcome everyone', 'text_ar' => 'أهلًا بالجميع', 'color' => 'blue', 'is_active' => true]);
        Announcement::create(['text' => 'Hidden message', 'color' => 'default', 'is_active' => false]);
        app(SettingRepository::class)->setMany([
            'whatsapp_enabled' => '1', 'whatsapp_number' => '966500000000',
            'telegram_enabled' => '1', 'telegram_username' => 'my_support',
        ]);

        $this->actingAs($customer)->post(route('customer.services.favorite', $service->id))->assertSessionHasNoErrors();
        $this->assertTrue($customer->favoriteServices()->whereKey($service->id)->exists());

        $this->actingAs($customer)->get(route('customer.services.index'))->assertOk()
            ->assertSee('Frequently used')
            ->assertSee('new-tag', false)
            ->assertSee('Welcome everyone')
            ->assertDontSee('Hidden message')
            ->assertSee('https://wa.me/966500000000', false)
            // Signed-in customers get WhatsApp only; Telegram is offered to visitors on the home page.
            ->assertDontSee('https://t.me/my_support', false);
        auth()->logout();
        $this->get('/')->assertOk()->assertSee('https://wa.me/966500000000', false)->assertSee('https://t.me/my_support', false);

        $this->actingAs($customer)->get(route('customer.services.show', $service->id))->assertOk()
            ->assertSee('Place an order')
            ->assertSee('Agree with terms of services')
            ->assertSee('In your frequently used list');

        // Switched off in the admin panel: bar and buttons disappear.
        $this->actingAs($this->admin())->post(route('admin.announcements.bar'), [])->assertSessionHasNoErrors();
        app(SettingRepository::class)->setMany(['whatsapp_enabled' => '0', 'telegram_enabled' => '0', 'new_badge_days' => '0']);
        $this->actingAs($customer)->get(route('customer.services.index'))->assertOk()
            ->assertDontSee('Welcome everyone')
            ->assertDontSee('wa.me', false)
            ->assertDontSee('new-tag', false);
    }

    public function test_admin_manages_announcements(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.announcements.store'), ['text' => 'Gift cards added', 'color' => 'green', 'icon' => '🎁', 'is_active' => 1])->assertSessionHasNoErrors();
        $item = Announcement::firstOrFail();
        $this->assertTrue($item->is_active);

        $this->actingAs($admin)->post(route('admin.announcements.toggle', $item->id));
        $this->assertFalse($item->fresh()->is_active);

        // A script address is refused.
        $this->actingAs($admin)->put(route('admin.announcements.update', $item->id), ['text' => 'x', 'color' => 'green', 'link' => 'javascript:alert(1)'])->assertSessionHasErrors('link');

        $this->actingAs($admin)->get(route('admin.announcements.index', ['edit' => $item->id]))->assertOk();
        $this->actingAs($admin)->delete(route('admin.announcements.destroy', $item->id));
        $this->assertSame(0, Announcement::count());
    }

    public function test_pages_render_in_arabic_right_to_left(): void
    {
        $customer = $this->customer();
        $admin = $this->admin();

        $this->withCookie('locale', 'ar')->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('dir="rtl"', false)->assertSee('الرئيسية')->assertSee('مزوّدو API');
        $this->withCookie('locale', 'ar')->actingAs($admin)->get(route('admin.settings'))->assertOk()->assertSee('إعدادات المنصة');
        $this->withCookie('locale', 'ar')->actingAs($customer)->get(route('customer.services.show', $this->service()->id))->assertOk()
            ->assertSee('تقديم الطلب')->assertSee('أوافق على شروط الخدمة');
    }

    public function test_provider_connection_sync_and_import(): void
    {
        $admin = $this->admin();
        Http::fake(function ($request) {
            return match ($request['action']) {
                'accountinfo' => Http::response(['SUCCESS' => [['MESSAGE' => 'Your Accout Info', 'AccoutInfo' => ['credit' => '12.50', 'currency' => 'USD', 'mail' => 'me@x.com']]]]),
                'imeiservicelist' => Http::response(['SUCCESS' => [['MESSAGE' => 'IMEI Service List', 'LIST' => [
                    'Free Group' => ['GROUPNAME' => 'Free Group', 'GROUPTYPE' => 'IMEI', 'SERVICES' => [
                        '11' => ['SERVICEID' => 11, 'SERVICETYPE' => 'IMEI', 'SERVICENAME' => 'Free IMEI Check', 'CREDIT' => '0.00', 'TIME' => 'Instant'],
                    ]],
                    'Server Group' => ['GROUPNAME' => 'Server Group', 'GROUPTYPE' => 'SERVER', 'SERVICES' => [
                        '22' => ['SERVICEID' => 22, 'SERVICETYPE' => 'SERVER', 'SERVICENAME' => 'Account Activation', 'CREDIT' => '10', 'TIME' => '1-5 Minutes',
                            'Requires.Custom' => [['fieldname' => 'Email', 'fieldtype' => 'text', 'required' => '1']]],
                    ]],
                ]]]]),
                default => Http::response(['ERROR' => [['MESSAGE' => 'Invalid Action']]]),
            };
        });

        $this->actingAs($admin)->post(route('admin.providers.store'), [
            'name' => 'GSM Free', 'url' => 'https://provider.test/', 'username' => 'me', 'api_key' => 'KEY-123', 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $provider = ApiProvider::firstOrFail();
        $this->assertSame('12.50', $provider->balance);
        $this->assertSame('KEY-123', $provider->api_key);
        // Stored encrypted, never in plain text.
        $this->assertStringNotContainsString('KEY-123', (string) \DB::table('api_providers')->value('api_key'));

        Http::assertSent(fn ($r) => $r->url() === 'https://provider.test/api/index.php' && $r['username'] === 'me' && $r['apiaccesskey'] === 'KEY-123');

        $this->actingAs($admin)->post(route('admin.providers.sync', $provider->id))->assertSessionHasNoErrors();
        $this->assertSame(2, $provider->remoteServices()->count());

        $category = \App\Models\ServiceCategory::where('slug', 'server-services')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.providers.import', $provider->id), [
            'remote_ids' => ['11', '22'], 'service_category_id' => $category->id, 'margin' => 50, 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $free = Service::where('remote_service_id', '11')->firstOrFail();
        $paid = Service::where('remote_service_id', '22')->firstOrFail();
        $this->assertSame('0.00', $free->price);
        $this->assertSame('15.00', $paid->price);
        $this->assertTrue($paid->usesProvider());
        $this->assertSame(['Email'], $paid->inputFields()->pluck('label')->all());
        $this->assertSame(['IMEI'], $free->inputFields()->pluck('label')->all());

        // Importing again does not duplicate.
        $this->actingAs($admin)->post(route('admin.providers.import', $provider->id), [
            'remote_ids' => ['11'], 'service_category_id' => $category->id, 'margin' => 0, 'status' => 'active',
        ]);
        $this->assertSame(1, Service::where('remote_service_id', '11')->count());

        foreach ([route('admin.providers.index'), route('admin.providers.create'), route('admin.providers.edit', $provider->id), route('admin.providers.services', $provider->id), route('admin.services.edit', $paid->id)] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertDontSee('KEY-123');
        }
    }

    public function test_order_is_forwarded_to_provider_and_completed_with_its_result(): void
    {
        $customer = $this->customer();
        $service = $this->service();
        $service->update(['api_provider_id' => $this->provider()->id, 'remote_service_id' => '134']);

        $status = '1';
        Http::fake(function ($request) use (&$status) {
            return match ($request['action']) {
                'placeimeiorder' => Http::response(['SUCCESS' => [['MESSAGE' => 'Order received', 'REFERENCEID' => '9001']]]),
                'getimeiorder' => Http::response(['SUCCESS' => [['IMEI' => '123456789012345', 'STATUS' => $status, 'CODE' => 'UNLOCK-CODE-777']]]),
            };
        });

        $this->order($customer, $service)->assertSessionHasNoErrors();
        $request = ServiceRequest::firstOrFail();
        $this->assertSame('9001', $request->remote_order_id);
        $this->assertSame('processing', $request->status);
        $this->assertSame('45.00', $this->balance($customer));
        Http::assertSent(fn ($r) => $r['action'] === 'placeimeiorder'
            && str_contains($r['parameters'], '<ID>134</ID>') && str_contains($r['parameters'], '<IMEI>123456789012345</IMEI>'));

        // Still in process at the provider: nothing changes.
        app(ProviderOrderService::class)->syncOpen();
        $this->assertSame('processing', $request->fresh()->status);

        $status = '4';
        $this->artisan('providers:sync-orders')->assertSuccessful();
        $request->refresh();
        $this->assertSame('completed', $request->status);
        $this->assertSame('UNLOCK-CODE-777', $request->response_data['result']);
        $this->assertSame('45.00', $this->balance($customer));

        $this->actingAs($customer)->get(route('customer.requests.show', $request->id))->assertOk()->assertSee('UNLOCK-CODE-777');
        $this->actingAs($this->admin())->get(route('admin.requests.show', $request->id))->assertOk()->assertSee('9001');
    }

    public function test_provider_rejection_refunds_once_and_place_errors_wait_for_the_admin(): void
    {
        $customer = $this->customer();
        $service = $this->service();
        $service->update(['api_provider_id' => $this->provider()->id, 'remote_service_id' => '134']);

        // 1) The provider refuses the order: the customer keeps waiting, the admin sees why.
        Http::fake(['*' => Http::response(['ERROR' => [['MESSAGE' => 'Not enough credits']]])]);
        $this->order($customer, $service)->assertSessionHasNoErrors();
        $first = ServiceRequest::firstOrFail();
        $this->assertSame('processing', $first->status);
        $this->assertNull($first->remote_order_id);
        $this->assertSame('error', $first->provider_status);
        $this->assertStringContainsString('Not enough credits', $first->provider_message);
        $this->assertSame('45.00', $this->balance($customer));
        // The provider's own problem is not shown to the customer.
        $this->actingAs($customer)->get(route('customer.requests.show', $first->id))->assertOk()->assertDontSee('Not enough credits');

        // 2) The admin sends it again and this time it is accepted, then rejected by the provider.
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(function ($request) {
            return match ($request['action']) {
                'placeimeiorder' => Http::response(['SUCCESS' => [['MESSAGE' => 'Order received', 'REFERENCEID' => '9002']]]),
                'getimeiorder' => Http::response(['SUCCESS' => [['STATUS' => '3', 'CODE' => 'Invalid IMEI']]]),
            };
        });
        $this->actingAs($this->admin())->post(route('admin.requests.provider.send', $first->id))->assertSessionHasNoErrors();
        $this->assertSame('9002', $first->fresh()->remote_order_id);

        $orders = app(ProviderOrderService::class);
        $orders->syncOpen();
        $orders->syncOpen();
        $first->refresh();
        $this->assertSame('failed', $first->status);
        $this->assertSame('Invalid IMEI', $first->error_message);
        $this->assertSame('50.00', $this->balance($customer));
        $this->assertSame(1, $first->transactions()->where('transaction_type', 'refund')->count());
    }

    public function test_inactive_provider_leaves_the_order_for_manual_handling(): void
    {
        $customer = $this->customer();
        $provider = $this->provider();
        $provider->update(['status' => 'inactive']);
        $service = $this->service();
        $service->update(['api_provider_id' => $provider->id, 'remote_service_id' => '134']);
        Http::fake();

        $this->order($customer, $service)->assertSessionHasNoErrors();
        Http::assertNothingSent();
        $this->assertSame('processing', ServiceRequest::firstOrFail()->status);
    }
}
