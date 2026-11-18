<?php

namespace Tests\Feature;

use App\Models\CustomerWallet;
use App\Models\User;
use App\Repositories\SettingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin > Settings > Maintenance mode: the site closes for visitors and customers, not for admins. */
class MaintenanceModeTest extends TestCase
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
        CustomerWallet::create(['user_id' => $user->id, 'balance' => 0]);

        return $user;
    }

    public function test_admin_turns_it_on_and_off_with_one_click(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Need help? Email us at');

        $this->actingAs($this->admin())->post(route('admin.maintenance'), ['enabled' => 1])->assertSessionHasNoErrors();
        $this->assertSame('1', app(SettingRepository::class)->get('maintenance_enabled'));
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->assertSee('Maintenance mode is on');

        $this->actingAs($this->admin())->post(route('admin.maintenance'), ['enabled' => 0]);
        $this->assertSame('0', app(SettingRepository::class)->get('maintenance_enabled'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'maintenance_on']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'maintenance_off']);
    }

    public function test_visitors_and_customers_see_the_maintenance_page_and_admins_see_the_site(): void
    {
        $customer = $this->customer();
        app(SettingRepository::class)->setMany([
            'maintenance_enabled' => '1',
            'maintenance_badge' => 'Back soon badge',
            'maintenance_text' => 'Custom English text',
            'maintenance_text_ar' => 'نص عربي مخصص',
            'maintenance_email' => 'help@example.com',
        ]);

        // Visitors: home, sign-in and registration all show the page.
        foreach (['/', '/login', '/register'] as $path) {
            $this->get($path)->assertStatus(503)->assertSee('Back soon badge')->assertSee('Custom English text')->assertSee('help@example.com');
        }
        // Signed-in customers too, including background calls.
        $this->actingAs($customer)->get(route('customer.dashboard'))->assertStatus(503)->assertSee('Custom English text');
        $this->actingAs($customer)->getJson(route('customer.requests.live'))->assertStatus(503);

        // Admins still get in and see everything.
        auth()->logout();
        $this->get(route('admin.login'))->assertOk();
        $this->actingAs($this->admin())->get(route('admin.settings'))->assertOk()->assertSee('Turn maintenance off');

        // The API for other servers keeps answering (with its own error, not the HTML page).
        $this->post('/api/index.php', ['action' => 'accountinfo'])->assertOk()->assertJsonPath('ERROR.0.MESSAGE', 'Authentication Failed');

        // Arabic visitors get the Arabic text (the cookie stays for the rest of this test, so it is last).
        auth()->logout();
        $this->withCookie('locale', 'ar')->get('/')->assertStatus(503)->assertSee('نص عربي مخصص')->assertSee('نجهّز لك تجربة أفضل');
    }

    public function test_shared_links_still_get_a_preview_while_maintenance_is_on(): void
    {
        app(SettingRepository::class)->setMany(['maintenance_enabled' => '1', 'site_name' => 'Unlock-SD', 'home_tagline' => 'Mobile unlocking']);

        // People and search engines: 503, but the page already carries the preview tags.
        $this->get('/')->assertStatus(503)
            ->assertSee('<meta property="og:title" content="Unlock-SD · Mobile unlocking">', false)
            ->assertSee('<meta property="og:description" content="Mobile unlocking">', false);

        // WhatsApp's link-preview fetch gets the same page with 200, so the preview is built.
        $this->withHeader('User-Agent', 'WhatsApp/2.23.20.0 A')->get('/')->assertOk()->assertSee('og:title', false);
        $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get('/login')->assertOk();
    }

    public function test_settings_form_saves_the_texts_and_switch(): void
    {
        $settings = app(SettingRepository::class)->all();

        $this->actingAs($this->admin())->put(route('admin.settings.update'), array_merge($settings, [
            'maintenance_enabled' => '1',
            'maintenance_text' => "Line one\nLine two",
            'maintenance_show_visits' => '0',
            'maintenance_email' => '',
        ]))->assertSessionHasNoErrors();

        $saved = app(SettingRepository::class);
        $this->assertSame('1', $saved->get('maintenance_enabled'));
        $this->assertSame("Line one\nLine two", $saved->get('maintenance_text'));
        auth()->logout();
        $this->get('/')->assertStatus(503)->assertSee('Line one')->assertDontSee('Visits:');
    }
}
