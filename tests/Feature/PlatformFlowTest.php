<?php

namespace Tests\Feature;

use App\Models\CreditTransaction;
use App\Models\CustomerWallet;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

/** End-to-end checks of the money rules and access rules from the blueprint. */
class PlatformFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function customer(string $username = 'ali', float $balance = 0): User
    {
        $user = new User([
            'first_name' => ucfirst($username), 'last_name' => 'Test', 'name' => ucfirst($username) . ' Test',
            'username' => $username, 'email' => "{$username}@example.com", 'password' => 'secret-pass-1',
        ]);
        $user->role = 'customer';
        $user->status = 'active';
        $user->save();
        CustomerWallet::create(['user_id' => $user->id, 'balance' => 0]);

        if ($balance > 0) {
            app(CreditService::class)->recharge($user, $balance, null, 'test', $this->admin()->id);
        }

        return $user;
    }

    private function imeiService(): Service
    {
        return Service::where('slug', 'haafedk-test-1')->firstOrFail(); // price 5.00, needs an IMEI
    }

    private function order(User $customer, Service $service, array $extra = [])
    {
        $fieldId = $service->fieldIds()[0];

        return $this->actingAs($customer)->post(route('customer.services.order', $service->id), array_merge([
            'idempotency_key' => (string) Str::uuid(),
            'fields' => [$fieldId => '123456789012345'],
            'terms' => '1',
        ], $extra));
    }

    public function test_guests_and_wrong_roles_are_kept_out(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/dashboard')->assertRedirect(route('login'));

        $customer = $this->customer();
        $this->actingAs($customer)->get('/admin')->assertRedirect(route('customer.dashboard'));
        $this->actingAs($customer)->get('/admin/customers')->assertRedirect(route('customer.dashboard'));
        $this->actingAs($this->admin())->get('/services')->assertRedirect(route('admin.dashboard'));
    }

    public function test_login_checks_portal_password_and_status(): void
    {
        $customer = $this->customer();

        // A customer cannot sign in through the admin portal.
        $this->post('/admin/login', ['login' => 'ali', 'password' => 'secret-pass-1'])->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->post('/login', ['login' => 'ali', 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->post('/login', ['login' => 'ali@example.com', 'password' => 'secret-pass-1'])->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($customer);

        // Suspended while signed in: the next request signs them out.
        // (actingAs with the updated model stands in for the fresh load a real request does.)
        $customer->status = 'suspended';
        $customer->save();
        $this->actingAs($customer)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_registration_creates_customer_with_empty_wallet(): void
    {
        $this->post('/register', [
            'username' => 'newuser', 'email' => 'new@example.com', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
            'first_name' => 'New', 'last_name' => 'User', 'address1' => '1 Main St', 'country' => 'Sudan', 'state' => 'Khartoum',
            'city' => 'Khartoum', 'zip_code' => '11111', 'phone' => '+249912345678', 'terms' => '1',
            'role' => 'admin', 'status' => 'active', // must be ignored
        ])->assertRedirect(route('login'));

        $user = User::where('username', 'newuser')->firstOrFail();
        $this->assertSame('customer', $user->role);
        $this->assertSame('New User', $user->name);
        $this->assertSame('0.00', $user->wallet->balance);
    }

    public function test_recharge_writes_ledger_and_adjustment_cannot_go_negative(): void
    {
        $customer = $this->customer();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.credits.store'), [
            'user_id' => $customer->id, 'operation' => 'recharge', 'amount' => '50.00', 'reference' => 'BANK-1', 'note' => 'Bank transfer',
        ])->assertRedirect(route('admin.customers.show', $customer->id));

        $entry = CreditTransaction::where('user_id', $customer->id)->firstOrFail();
        $this->assertSame(['recharge', '50.00', '0.00', '50.00', $admin->id], [$entry->transaction_type, $entry->amount, $entry->balance_before, $entry->balance_after, $entry->performed_by]);
        $this->assertSame('50.00', $customer->wallet->fresh()->balance);

        // Taking back more than the balance is refused and changes nothing.
        $this->actingAs($admin)->post(route('admin.credits.store'), [
            'user_id' => $customer->id, 'operation' => 'adjustment', 'amount' => '-80', 'note' => 'mistake',
        ])->assertSessionHasErrors('amount');
        $this->assertSame('50.00', $customer->wallet->fresh()->balance);
        $this->assertSame(1, CreditTransaction::count());

        // A customer cannot recharge themselves.
        $this->actingAs($customer)->post(route('admin.credits.store'), ['user_id' => $customer->id, 'operation' => 'recharge', 'amount' => '999']);
        $this->assertSame('50.00', $customer->wallet->fresh()->balance);
    }

    public function test_ledger_entries_cannot_be_edited_or_deleted(): void
    {
        $this->customer('ali', 10);
        $entry = CreditTransaction::firstOrFail();

        $this->expectException(LogicException::class);
        $entry->update(['amount' => 9999]);
    }

    public function test_order_deducts_database_price_and_validates_inputs(): void
    {
        $customer = $this->customer('ali', 20);
        $service = $this->imeiService();

        // Bad IMEI: nothing is charged.
        $this->order($customer, $service, ['fields' => [$service->fieldIds()[0] => '123']])->assertSessionHasErrors();
        $this->assertSame(0, ServiceRequest::count());
        $this->assertSame('20.00', $customer->wallet->fresh()->balance);

        // A price sent by the browser is ignored.
        $this->order($customer, $service, ['price' => '0.01', 'amount' => '0.01'])->assertSessionHasNoErrors();

        $request = ServiceRequest::firstOrFail();
        $this->assertSame(['processing', '5.00'], [$request->status, $request->amount]);
        $this->assertSame('123456789012345', $request->request_data[0]['value']);
        $this->assertSame('15.00', $customer->wallet->fresh()->balance);

        $deduction = CreditTransaction::where('transaction_type', 'deduction')->firstOrFail();
        $this->assertSame(['-5.00', '20.00', '15.00', $request->id], [$deduction->amount, $deduction->balance_before, $deduction->balance_after, $deduction->service_request_id]);
    }

    public function test_insufficient_credit_creates_no_request(): void
    {
        $customer = $this->customer('ali', 4.99);

        $this->order($customer, $this->imeiService())->assertSessionHasErrors('order');

        $this->assertSame(0, ServiceRequest::count());
        $this->assertSame('4.99', $customer->wallet->fresh()->balance);
        $this->assertSame(0, CreditTransaction::where('transaction_type', 'deduction')->count());
    }

    public function test_double_submit_is_charged_once(): void
    {
        $customer = $this->customer('ali', 20);
        $key = (string) Str::uuid();

        $this->order($customer, $this->imeiService(), ['idempotency_key' => $key]);
        $this->order($customer, $this->imeiService(), ['idempotency_key' => $key]);

        $this->assertSame(1, ServiceRequest::count());
        $this->assertSame('15.00', $customer->wallet->fresh()->balance);
    }

    public function test_inactive_service_cannot_be_ordered(): void
    {
        $customer = $this->customer('ali', 20);
        $service = $this->imeiService();
        $service->update(['status' => 'inactive']);

        $this->order($customer, $service)->assertSessionHasErrors('order');
        $this->assertSame('20.00', $customer->wallet->fresh()->balance);
    }

    public function test_failed_request_is_refunded_once_and_final(): void
    {
        $customer = $this->customer('ali', 20);
        $this->order($customer, $this->imeiService());
        $request = ServiceRequest::firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.requests.update', $request->id), ['status' => 'failed', 'error' => 'Not found'])->assertSessionHasNoErrors();

        $this->assertSame('20.00', $customer->wallet->fresh()->balance);
        $this->assertNotNull($request->fresh()->refunded_at);

        // The deduction row is untouched; the refund is its own row.
        $this->assertSame(['recharge', 'deduction', 'refund'], CreditTransaction::orderBy('id')->pluck('transaction_type')->all());

        // A closed request cannot be reopened or refunded again.
        $this->actingAs($admin)->put(route('admin.requests.update', $request->id), ['status' => 'cancelled'])->assertSessionHasErrors('status');
        $this->actingAs($admin)->put(route('admin.requests.update', $request->id), ['status' => 'completed', 'result' => 'x'])->assertSessionHasErrors('status');
        $this->assertSame(1, CreditTransaction::where('transaction_type', 'refund')->count());
        $this->assertSame('20.00', $customer->wallet->fresh()->balance);
    }

    public function test_completed_request_shows_result_and_keeps_the_money(): void
    {
        $customer = $this->customer('ali', 20);
        $this->order($customer, $this->imeiService());
        $request = ServiceRequest::firstOrFail();

        // Completing needs a result.
        $this->actingAs($this->admin())->put(route('admin.requests.update', $request->id), ['status' => 'completed'])->assertSessionHasErrors('result');
        $this->actingAs($this->admin())->put(route('admin.requests.update', $request->id), ['status' => 'completed', 'result' => 'CODE-778899']);

        $this->assertSame('completed', $request->fresh()->status);
        $this->assertSame('15.00', $customer->wallet->fresh()->balance);
        $this->actingAs($customer)->get(route('customer.requests.show', $request->id))->assertOk()->assertSee('CODE-778899');
    }

    public function test_new_order_goes_straight_to_processing_and_customer_cannot_cancel(): void
    {
        $ali = $this->customer('ali', 20);
        $omar = $this->customer('omar', 20);
        $this->order($ali, $this->imeiService());
        $request = ServiceRequest::firstOrFail();

        // Work starts at once: no pending stage, no cancel button, no cancel address.
        $this->assertSame('processing', $request->status);
        $this->actingAs($ali)->get(route('customer.requests.show', $request->id))->assertOk()
            ->assertDontSee('Cancel request and refund');
        $this->assertContains($this->post('/requests/' . $request->id . '/cancel')->status(), [404, 405]);
        $this->assertSame('processing', $request->fresh()->status);
        $this->assertSame('15.00', $ali->wallet->fresh()->balance);

        // Someone else's request looks like it does not exist.
        $this->actingAs($omar)->get(route('customer.requests.show', $request->id))->assertNotFound();

        // The admin still sees it as work to do, and can still cancel it with a refund.
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->assertSee('Orders to process');
        $this->actingAs($this->admin())->put(route('admin.requests.update', $request->id), ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertSame('20.00', $ali->wallet->fresh()->balance);
    }

    public function test_admin_manages_categories_services_and_customers(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Unlock Services', 'status' => 'active',
            'fields' => [
                ['label' => 'IMEI', 'input_type' => 'imei', 'is_required' => '1'],
                ['label' => 'Email', 'input_type' => 'email', 'is_required' => '0'],
            ],
        ])->assertRedirect(route('admin.categories.index'));

        $category = \App\Models\ServiceCategory::where('slug', 'unlock-services')->firstOrFail();
        $this->assertSame(['IMEI', 'Email'], $category->fields->pluck('label')->all());

        $this->actingAs($admin)->post(route('admin.services.store'), [
            'service_category_id' => $category->id, 'name' => 'Fast Unlock', 'price' => '12.50', 'status' => 'active',
            'field_ids' => [$category->fields[0]->id, 999999], // a field from nowhere is dropped
        ])->assertRedirect(route('admin.services.index'));

        $service = Service::where('slug', 'fast-unlock')->firstOrFail();
        $this->assertSame([$category->fields[0]->id], $service->fieldIds());

        // Removing a category field also detaches it from its services.
        $this->actingAs($admin)->put(route('admin.categories.update', $category->id), [
            'name' => 'Unlock Services', 'status' => 'active',
            'fields' => [['id' => $category->fields[1]->id, 'label' => 'Email', 'input_type' => 'email', 'is_required' => '1']],
        ])->assertSessionHasNoErrors();
        $this->assertSame([], $service->fresh()->fieldIds());

        // A category with services cannot be deleted.
        $this->actingAs($admin)->delete(route('admin.categories.destroy', $category->id))->assertSessionHasErrors('category');

        $this->actingAs($admin)->post(route('admin.services.toggle', $service->id));
        $this->assertSame('inactive', $service->fresh()->status);

        $this->actingAs($admin)->post(route('admin.customers.store'), [
            'first_name' => 'Sara', 'username' => 'sara', 'email' => 'sara@example.com', 'password' => 'secret-pass-1', 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $sara = User::where('username', 'sara')->firstOrFail();
        $this->assertNotNull($sara->wallet);

        $this->actingAs($admin)->post(route('admin.customers.status', $sara->id), ['status' => 'suspended']);
        $this->assertSame('suspended', $sara->fresh()->status);

        // Customer screens never load an admin account.
        $this->actingAs($admin)->get(route('admin.customers.show', $admin->id))->assertNotFound();
    }

    public function test_admin_notification_bell_shows_customer_activity_not_own_actions(): void
    {
        $admin = $this->admin();
        // The recharge inside customer() is the admin's own action and must not be notified.
        $customer = $this->customer('ali', 30);
        $this->order($customer, $this->imeiService())->assertSessionHasNoErrors();

        $data = $this->actingAs($admin)->getJson(route('admin.notifications.index'))->assertOk()->json();
        $this->assertSame(1, $data['unread']);
        $this->assertStringContainsString('New order', $data['html']);
        $this->assertStringNotContainsString('Credit recharged', $data['html']);

        // The bell is in the admin top bar with its count.
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('data-notif-count', false);

        $this->actingAs($admin)->postJson(route('admin.notifications.read'))->assertOk();
        $this->assertSame(0, $this->actingAs($admin)->getJson(route('admin.notifications.index'))->json('unread'));

        // Customers cannot read the admin's notifications: the role check sends them back to their own area.
        $this->actingAs($customer)->getJson(route('admin.notifications.index'))->assertRedirect()->assertDontSee('New order');
    }

    public function test_home_page_for_visitors_is_controlled_from_settings(): void
    {
        $admin = $this->admin();

        // Visitors get the home page; signed-in users go to their own dashboard.
        $this->get('/')->assertOk()->assertSee('data-home-popup', false)->assertSee(route('register'), false);
        $this->actingAs($admin)->get('/')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->customer('ali'))->get('/')->assertRedirect(route('customer.dashboard'));
        auth()->logout();

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'site_name' => 'Fast Unlock', 'new_account_status' => 'active', 'new_badge_days' => 7,
            'allow_registration' => '1',
            'whatsapp_enabled' => '1', 'whatsapp_number' => '+249 112 758 148',
            'telegram_enabled' => '1', 'telegram_username' => '@fastunlock',
            'contact_phone' => '+249 112 758 148', 'contact_email' => 'help@example.com',
            'home_popup_enabled' => '0',
            'home_offer_enabled' => '1', 'home_offer_title' => 'Half price today', 'home_offer_ends_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();
        auth()->logout();

        $this->get('/')->assertOk()
            ->assertSee('Fast Unlock')
            ->assertSee('Half price today')
            ->assertSee('data-countdown', false)
            ->assertSee('https://wa.me/249112758148', false)
            ->assertSee('https://t.me/fastunlock', false)
            ->assertSee('help@example.com')
            ->assertDontSee('data-home-popup', false);

        // An offer that has ended is hidden; chat buttons switched off disappear.
        app(\App\Repositories\SettingRepository::class)->setMany([
            'home_offer_ends_at' => now()->subHour()->format('Y-m-d\TH:i'), 'whatsapp_enabled' => '0', 'telegram_enabled' => '0',
        ]);
        $this->get('/')->assertOk()->assertDontSee('Half price today')->assertDontSee('wa.me', false)->assertDontSee('t.me/', false);
    }

    public function test_vip_tag_instant_tag_statement_and_guide(): void
    {
        $admin = $this->admin();
        $customer = $this->customer('ali', 30);
        $service = $this->imeiService();

        // Only the admin can tag a customer; it shows before the email in the customer's menu.
        $this->actingAs($admin)->put(route('admin.customers.update', $customer->id), [
            'first_name' => 'Ali', 'username' => 'ali', 'email' => 'ali@example.com', 'customer_tag' => 'VIP',
        ])->assertSessionHasNoErrors();
        $customer = $customer->fresh();
        $this->assertSame('VIP', $customer->customer_tag);
        $this->actingAs($customer)->get(route('customer.dashboard'))->assertOk()->assertSee('(VIP)');

        // A customer saving their own profile cannot give themselves a tag.
        $this->actingAs($customer)->put(route('customer.profile.update'), [
            'first_name' => 'Ali', 'email' => 'ali@example.com', 'customer_tag' => 'Owner',
        ]);
        $this->assertSame('VIP', $customer->fresh()->customer_tag);

        // A normal customer shows no tag.
        $plain = $this->customer('sara');
        $this->actingAs($plain)->get(route('customer.dashboard'))->assertOk()->assertDontSee('(VIP)');

        // Instant services get the yellow tag in the catalog.
        $service->update(['is_instant' => true]);
        $this->actingAs($customer)->get(route('customer.services.index'))->assertOk()->assertSee('tag-instant', false);

        // Statement: CR for money in, DR for money out, and the Excel export.
        $this->order($customer, $service)->assertSessionHasNoErrors();
        $this->actingAs($customer)->get(route('customer.credits'))->assertOk()
            ->assertSee('New Order Placed')->assertSee('+ Add Funds')->assertSee('>CR<', false)->assertSee('>DR<', false);
        $csv = $this->actingAs($customer)->get(route('customer.credits.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('New Order Placed', $csv);
        $this->assertStringNotContainsString('sara', $csv);

        // The guide follows the chosen language.
        $this->actingAs($customer)->get(route('customer.guide'))->assertOk()->assertSee('User Guide')->assertSee('*#06#');
        $this->actingAs($customer)->withCookie('locale', 'ar')->get(route('customer.guide'))->assertOk()->assertSee('دليل الاستخدام');
    }

    public function test_terms_and_privacy_pages_in_both_languages(): void
    {
        app(\App\Repositories\SettingRepository::class)->setMany(['terms_text' => 'No orders on Fridays.', 'contact_email' => 'help@example.com']);

        // Public: visitors can read them, in English or Arabic.
        $this->get(route('legal.terms'))->assertOk()->assertSee('Terms of Service')->assertSee('cannot be cancelled')
            ->assertSee('No orders on Fridays.')->assertSee('help@example.com');
        $this->get(route('legal.privacy'))->assertOk()->assertSee('Privacy Policy')->assertSee('hashed');
        $this->withCookie('locale', 'ar')->get(route('legal.terms'))->assertOk()->assertSee('شروط الخدمة');
        $this->withCookie('locale', 'ar')->get(route('legal.privacy'))->assertOk()->assertSee('سياسة الخصوصية');

        // Linked from the home page, sign-in pages and the customer area.
        $this->get('/')->assertSee(route('legal.terms'), false)->assertSee(route('legal.privacy'), false);
        $this->get(route('login'))->assertSee(route('legal.privacy'), false);
        // Visitors get the normal guest menu with Registration / Login, no Dashboard button.
        auth()->logout();
        $this->get(route('legal.terms'))->assertOk()->assertSee('guest-drawer', false)->assertSee(route('login'), false)->assertDontSee('>Dashboard<', false);

        // Signed-in customers read them inside their normal panel, with their own menu.
        $this->actingAs($this->customer('ali'))->get(route('customer.dashboard'))->assertSee(route('legal.terms'), false);
        $this->get(route('legal.terms'))->assertOk()->assertSee('cnav-head', false)->assertSee(route('customer.requests.index'), false);
        $this->actingAs($this->admin())->get(route('legal.privacy'))->assertOk()->assertSee(route('admin.customers.index'), false);
    }

    public function test_server_tools_are_admin_only_and_run_only_known_commands(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.tools.index'))->assertOk()
            ->assertSee('Server Tools')->assertSee('php artisan optimize:clear')->assertSee('Database backup');

        // A harmless tool runs, shows its output and is logged.
        $this->actingAs($admin)->post(route('admin.tools.run', 'view-clear'))->assertRedirect(route('admin.tools.index'))
            ->assertSessionHas('tool_result', fn ($r) => $r['ok'] === true && $r['tool'] === 'view-clear');
        $this->assertDatabaseHas('activity_logs', ['action' => 'server_tool', 'user_id' => $admin->id]);

        // Anything not on the list does not exist.
        $this->actingAs($admin)->post('/admin/tools/rm-rf')->assertNotFound();

        // Backup: written privately, downloadable by the admin, then deleted.
        $this->actingAs($admin)->post(route('admin.tools.run', 'backup'))->assertSessionHas('tool_result', fn ($r) => $r['ok'] === true);
        $backups = app(\App\Services\ServerToolService::class)->backups();
        $this->assertNotEmpty($backups);
        $name = $backups[0]['name'];
        $sql = file_get_contents(app(\App\Services\ServerToolService::class)->backupPath($name));
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('INSERT INTO', $sql);
        $this->actingAs($admin)->get(route('admin.tools.backups.download', $name))->assertOk();
        $this->actingAs($admin)->get('/admin/tools/backups/..%2F.env')->assertNotFound();
        $this->actingAs($admin)->delete(route('admin.tools.backups.destroy', $name))->assertSessionHasNoErrors();
        $this->assertNull(app(\App\Services\ServerToolService::class)->backupPath($name));

        // Customers never reach the tools.
        $customer = $this->customer('ali');
        $this->actingAs($customer)->get(route('admin.tools.index'))->assertRedirect();
        $this->actingAs($customer)->post(route('admin.tools.run', 'view-clear'))->assertRedirect();
    }

    public function test_every_page_renders(): void
    {
        // Guest pages first, before anyone is signed in.
        foreach (['/login', '/register', '/forgot-password', '/admin/login', '/reset-password/abc?email=a@b.c'] as $url) {
            $this->get($url)->assertOk();
        }

        $customer = $this->customer('ali', 30);
        $this->order($customer, $this->imeiService());
        $request = ServiceRequest::firstOrFail();
        $service = $this->imeiService();
        $admin = $this->admin();

        $adminPages = [
            route('admin.dashboard'), route('admin.customers.index', ['q' => 'ali', 'status' => 'active']), route('admin.customers.create'),
            route('admin.customers.show', $customer->id), route('admin.customers.edit', $customer->id),
            route('admin.credits.index', ['type' => 'recharge']), route('admin.credits.create', ['customer' => $customer->id]),
            route('admin.categories.index'), route('admin.categories.create'), route('admin.categories.edit', $service->service_category_id),
            route('admin.services.index'), route('admin.services.create'), route('admin.services.edit', $service->id),
            route('admin.requests.index', ['status' => 'pending']), route('admin.requests.show', $request->id),
            route('admin.reports.index'), route('admin.reports.index', ['period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-02-01']),
            route('admin.reports.index', ['period' => 'custom', 'from' => 'garbage']),
            route('admin.activity'), route('admin.settings'), route('admin.profile'),
        ];
        foreach ($adminPages as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        foreach ([route('admin.customers.export'), route('admin.credits.export'), route('admin.requests.export'), route('admin.reports.export')] as $url) {
            $response = $this->actingAs($admin)->get($url)->assertOk();
            $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
            $this->assertNotEmpty($response->streamedContent());
        }

        $customerPages = [
            route('customer.dashboard'), route('customer.services.index'), route('customer.services.show', $service->id),
            route('customer.requests.index'), route('customer.requests.show', $request->id), route('customer.credits'), route('customer.profile'),
        ];
        foreach ($customerPages as $url) {
            $this->actingAs($customer)->get($url)->assertOk();
        }
    }
}
