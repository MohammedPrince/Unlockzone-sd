<?php

namespace Tests\Feature;

use App\Mail\SiteMail;
use App\Models\CustomerWallet;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\CreditService;
use App\Support\EnvFile;
use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Emails to the admin (new customer, new order) and to the customer (order finished), and the email settings form. */
class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private string $envDir;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutDefer();
        config(['mail.admin_address' => 'boss@example.com, second@example.com']);

        // Never touch the real .env: the settings form writes to a copy in a temp folder.
        $this->envDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mail-env-' . uniqid();
        mkdir($this->envDir);
        file_put_contents($this->envDir . '/.env', "APP_NAME=\"Test\"\nMAIL_MAILER=log\nMAIL_HOST=127.0.0.1\nMAIL_PASSWORD=old-secret\n");
        $this->app->useEnvironmentPath($this->envDir);
    }

    protected function tearDown(): void
    {
        @unlink($this->envDir . '/.env');
        @rmdir($this->envDir);

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function customer(float $balance = 20): User
    {
        $user = new User(['first_name' => 'Ali', 'last_name' => 'Test', 'name' => 'Ali Test', 'username' => 'ali', 'email' => 'ali@example.com', 'password' => 'secret-pass-1']);
        $user->role = 'customer';
        $user->status = 'active';
        $user->save();
        CustomerWallet::create(['user_id' => $user->id, 'balance' => 0]);
        app(CreditService::class)->recharge($user, $balance, null, 'test', $this->admin()->id);

        return $user;
    }

    private function order(User $customer): ServiceRequest
    {
        $service = Service::where('slug', 'haafedk-test-1')->firstOrFail();
        $this->actingAs($customer)->post(route('customer.services.order', $service->id), [
            'idempotency_key' => (string) Str::uuid(),
            'fields' => [$service->fieldIds()[0] => '123456789012345'],
            'terms' => '1',
        ])->assertSessionHasNoErrors();

        return ServiceRequest::latest('id')->firstOrFail();
    }

    public function test_admin_gets_email_when_a_customer_registers(): void
    {
        $this->post('/register', [
            'username' => 'newuser', 'email' => 'new@example.com', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
            'first_name' => 'New', 'last_name' => 'User',
            'phone' => '+249912345678', 'terms' => '1',
        ])->assertRedirect(route('login'));

        Mail::assertSent(SiteMail::class, fn (SiteMail $m) => $m->hasTo('boss@example.com') && $m->hasTo('second@example.com')
            && str_contains($m->mailSubject, 'newuser') && $m->details['Email'] === 'new@example.com');
    }

    private function registerNewUser(): User
    {
        $this->post('/register', [
            'username' => 'newuser', 'email' => 'new@example.com', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
            'first_name' => 'New', 'last_name' => 'User',
            'phone' => '+249912345678', 'terms' => '1',
        ])->assertRedirect(route('login'));

        return User::where('username', 'newuser')->firstOrFail();
    }

    private function confirmLink(): string
    {
        $url = null;
        Mail::assertSent(SiteMail::class, function (SiteMail $m) use (&$url) {
            if ($m->hasTo('new@example.com') && str_contains($m->mailSubject, 'Confirm your email')) {
                $url = $m->buttonUrl;
            }

            return $url !== null;
        });

        return $url;
    }

    public function test_confirming_the_email_activates_the_account(): void
    {
        $customer = $this->registerNewUser();
        $this->assertSame('pending', $customer->status);

        // Not confirmed yet: sign in is refused with a hint.
        $this->post('/login', ['login' => 'newuser', 'password' => 'secret-pass-1'])->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->get($this->confirmLink())->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Your email is confirmed. Now you can sign in.');

        $customer->refresh();
        $this->assertSame('active', $customer->status);
        $this->assertNotNull($customer->email_verified_at);

        $this->post('/login', ['login' => 'newuser', 'password' => 'secret-pass-1'])->assertRedirect(route('customer.dashboard'));
    }

    public function test_confirmation_link_expires_after_five_minutes_and_admin_must_activate(): void
    {
        $customer = $this->registerNewUser();
        $link = $this->confirmLink();

        $this->travel(6)->minutes();

        $this->get($link)->assertRedirect(route('login'))->assertSessionHasErrors('login');
        $this->assertSame('pending', $customer->fresh()->status);
        $this->assertNull($customer->fresh()->email_verified_at);
    }

    public function test_tampered_link_is_refused_and_suspended_account_stays_suspended(): void
    {
        $customer = $this->registerNewUser();
        $link = $this->confirmLink();

        $this->get(str_replace('/' . $customer->id . '/', '/' . $this->admin()->id . '/', $link))->assertSessionHasErrors('login');
        $this->assertSame('pending', $customer->fresh()->status);

        $customer->forceFill(['status' => 'suspended'])->save();
        $this->get($link)->assertRedirect(route('login'));
        $this->assertSame('suspended', $customer->fresh()->status);
    }

    public function test_confirmed_email_still_waits_for_admin_when_settings_say_pending(): void
    {
        app(\App\Repositories\SettingRepository::class)->setMany(['new_account_status' => 'pending']);

        $customer = $this->registerNewUser();
        $this->get($this->confirmLink())->assertSessionHas('success', 'Your email is confirmed. Your account is waiting for administrator activation.');

        $this->assertSame('pending', $customer->fresh()->status);
        $this->assertNotNull($customer->fresh()->email_verified_at);
    }

    public function test_no_admin_email_when_no_admin_address_is_set(): void
    {
        config(['mail.admin_address' => '']);
        $this->order($this->customer());

        Mail::assertNothingSent();
    }

    public function test_admin_gets_email_for_a_new_order_and_customer_gets_the_result(): void
    {
        $request = $this->order($customer = $this->customer());

        Mail::assertSent(SiteMail::class, fn (SiteMail $m) => $m->hasTo('boss@example.com')
            && str_contains($m->mailSubject, $request->reference) && in_array('123456789012345', $m->details, true));

        $this->actingAs($this->admin())->put(route('admin.requests.update', $request->id), ['status' => 'completed', 'result' => 'CODE-778899'])->assertSessionHasNoErrors();

        // The customer gets the result with the order details (including the IMEI they typed); the admin gets a hidden copy.
        Mail::assertSent(SiteMail::class, fn (SiteMail $m) => $m->hasTo($customer->email) && $m->code === 'CODE-778899'
            && str_contains($m->mailSubject, 'completed') && $m->tone === 'success'
            && $m->hasBcc('boss@example.com') && !$m->hasTo('boss@example.com')
            && ($m->details['Order ID'] ?? null) === $request->reference && in_array('123456789012345', $m->details, true));
        Mail::assertSent(SiteMail::class, 2);

        // The admin's own new-order email is not copied to the admin a second time.
        Mail::assertSent(SiteMail::class, fn (SiteMail $m) => $m->hasTo('boss@example.com') && !$m->hasBcc('boss@example.com'));

        // The branded template renders with the subject as its title, the code, the details and the button.
        $html = (new SiteMail('Your order R-1 is completed', 'Your order is completed', ['Hello'], ['Order ID' => 'R-1'], 'CODE-778899', 'View order', 'https://x.test/o/1', 'success'))->render();
        $this->assertStringContainsString('>Your order R-1 is completed</h1>', $html);
        $this->assertStringContainsString('CODE-778899', $html);
        $this->assertStringContainsString('#16a34a', $html);
        $this->assertStringContainsString('https://x.test/o/1', $html);
    }

    public function test_customer_is_told_about_a_failed_order_and_the_refund(): void
    {
        $request = $this->order($customer = $this->customer());

        $this->actingAs($this->admin())->put(route('admin.requests.update', $request->id), ['status' => 'failed', 'error' => 'Not found'])->assertSessionHasNoErrors();

        Mail::assertSent(SiteMail::class, fn (SiteMail $m) => $m->hasTo($customer->email) && $m->code === null && $m->hasBcc('boss@example.com')
            && collect($m->lines)->contains(fn ($l) => str_contains($l, 'Reason: Not found'))
            && collect($m->lines)->contains(fn ($l) => str_contains($l, 'returned to your credit')));
    }

    public function test_password_reset_email_uses_the_site_template_and_is_not_copied_to_the_admin(): void
    {
        $customer = $this->customer();

        $this->post(route('password.email'), ['email' => $customer->email])->assertSessionHasNoErrors();

        Mail::assertSent(SiteMail::class, fn (SiteMail $m) => $m->hasTo($customer->email)
            && str_starts_with($m->mailSubject, 'Reset your password')
            && str_contains((string) $m->buttonUrl, '/reset-password/')
            && !$m->hasBcc('boss@example.com'));
    }

    public function test_a_mail_server_error_does_not_break_the_order(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $request = $this->order($this->customer());

        $this->assertSame('processing', $request->status);
    }

    public function test_admin_saves_email_settings_to_env_and_keeps_password_when_empty(): void
    {
        $this->actingAs($this->admin())->put(route('admin.tools.mail.save'), [
            'admin_address' => 'boss@unlock-sd.com, other@unlock-sd.com',
            'mailer' => 'smtp', 'host' => 'mail.unlock-sd.com', 'port' => 465, 'encryption' => 'ssl',
            'username' => 'support@unlock-sd.com', 'password' => 'p@ss"word$HOME\\x #1',
            'from_address' => 'support@unlock-sd.com', 'from_name' => 'Unlock SD',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $env = Dotenv::parse(file_get_contents($this->envDir . '/.env'));
        $this->assertSame('smtp', $env['MAIL_MAILER']);
        $this->assertSame('smtps', $env['MAIL_SCHEME']);
        $this->assertSame('mail.unlock-sd.com', $env['MAIL_HOST']);
        $this->assertSame('p@ss"word$HOME\\x #1', $env['MAIL_PASSWORD']); // stored exactly, not expanded
        $this->assertSame('boss@unlock-sd.com,other@unlock-sd.com', $env['MAIL_ADMIN_ADDRESS']);
        $this->assertSame('Test', $env['APP_NAME']); // other lines untouched

        // Empty password box: the saved password stays.
        $this->actingAs($this->admin())->put(route('admin.tools.mail.save'), [
            'mailer' => 'smtp', 'host' => 'mail.unlock-sd.com', 'port' => 587, 'encryption' => 'tls',
            'username' => 'support@unlock-sd.com', 'password' => '', 'from_address' => 'support@unlock-sd.com',
        ])->assertSessionHasNoErrors();

        $env = Dotenv::parse(file_get_contents($this->envDir . '/.env'));
        $this->assertSame('p@ss"word$HOME\\x #1', $env['MAIL_PASSWORD']);
        $this->assertSame('smtp', $env['MAIL_SCHEME']);
        $this->assertSame('', $env['MAIL_ADMIN_ADDRESS']);

        // The password is never written to the page.
        $this->actingAs($this->admin())->get(route('admin.tools.index'))->assertOk()
            ->assertSee('Email settings')->assertDontSee('p@ss', false);
    }

    public function test_email_settings_reject_bad_input_and_non_admins(): void
    {
        $admin = $this->admin();
        $before = file_get_contents($this->envDir . '/.env');

        $this->actingAs($admin)->put(route('admin.tools.mail.save'), [
            'admin_address' => 'not-an-email', 'mailer' => 'smtp', 'host' => "evil\nAPP_DEBUG=true", 'port' => 465,
            'encryption' => 'ssl', 'from_address' => 'support@unlock-sd.com', 'password' => "x\nAPP_KEY=y",
        ])->assertSessionHasErrors(['admin_address', 'host', 'password']);
        $this->assertSame($before, file_get_contents($this->envDir . '/.env'));

        $this->actingAs($this->customer())->put(route('admin.tools.mail.save'), ['mailer' => 'log'])->assertRedirect();
        $this->assertSame($before, file_get_contents($this->envDir . '/.env'));
    }

    public function test_test_email_is_sent_at_once(): void
    {
        $this->actingAs($this->admin())->post(route('admin.tools.mail.test'), ['to' => 'me@example.com'])
            ->assertSessionHas('success');

        Mail::assertSent(SiteMail::class, fn (SiteMail $m) => $m->hasTo('me@example.com') && str_contains($m->mailSubject, 'Test email'));
    }

    public function test_env_file_quoting_round_trips(): void
    {
        foreach (['plain', 'with space', 'a"b', 'c\\d', '$VAR ${X}', "it's #hash", ''] as $value) {
            $this->assertSame($value, Dotenv::parse('K=' . EnvFile::quote($value))['K']);
        }

        $this->expectException(\RuntimeException::class);
        EnvFile::quote("two\nlines");
    }
}
