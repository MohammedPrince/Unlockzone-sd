<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Visitors by source (Facebook, WhatsApp, Telegram...) and the sign-ups each source brought. */
class VisitTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const PHONE = 'Mozilla/5.0 (Linux; Android 10; SM-J600F) AppleWebKit/537.36 Chrome/120.0 Mobile Safari/537.36';

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_source_comes_from_link_tag_in_app_browser_or_referrer(): void
    {
        $this->withHeader('User-Agent', self::PHONE)->get('/?ref=wa')->assertOk();
        $this->assertSame('whatsapp', Visit::latest('id')->value('source'));

        $this->flushSession();
        $this->withHeaders(['User-Agent' => self::PHONE, 'Referer' => 'https://l.facebook.com/l.php?u=x'])->get('/login');
        $visit = Visit::latest('id')->first();
        $this->assertSame(['facebook', 'l.facebook.com'], [$visit->source, $visit->referrer_host]);

        $this->flushSession();
        $this->withHeaders(['User-Agent' => self::PHONE . ' [FBAN/FB4A;FBAV/400.0]', 'Referer' => ''])->get('/');
        $this->assertSame('facebook', Visit::latest('id')->value('source'));

        $this->flushSession();
        $this->withHeaders(['User-Agent' => self::PHONE, 'Referer' => 'https://www.google.com.sa/'])->get('/');
        $this->assertSame('google', Visit::latest('id')->value('source'));

        $this->flushSession();
        $this->withHeaders(['User-Agent' => self::PHONE, 'Referer' => 'https://t.me/unlocksd'])->get('/');
        $this->assertSame('telegram', Visit::latest('id')->value('source'));
    }

    public function test_one_visit_per_session_and_bots_admins_and_admin_pages_are_not_counted(): void
    {
        $this->withHeader('User-Agent', self::PHONE)->get('/?ref=telegram');
        $this->get('/login');
        $this->get('/register');
        $this->assertSame(1, Visit::count());

        $this->flushSession();
        $this->withHeader('User-Agent', 'WhatsApp/2.23.20.0 A')->get('/');
        $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get('/');
        $this->withHeader('User-Agent', 'TelegramBot (like TwitterBot)')->get('/');
        $this->assertSame(1, Visit::count());

        $this->flushSession();
        $this->withHeader('User-Agent', self::PHONE)->get('/admin/login');
        $this->actingAs($this->admin())->get('/admin');
        $this->assertSame(1, Visit::count());
    }

    public function test_sign_up_remembers_its_source_and_dashboard_shows_the_numbers(): void
    {
        $this->withHeader('User-Agent', self::PHONE)->get('/?ref=facebook');
        $this->post('/register', [
            'username' => 'fbuser', 'email' => 'fb@example.com', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
            'first_name' => 'Fb', 'last_name' => 'User', 'address1' => '1 Main St', 'country' => 'Sudan', 'state' => 'Khartoum',
            'city' => 'Khartoum', 'zip_code' => '11111', 'phone' => '+249912345678', 'terms' => '1',
        ])->assertRedirect(route('login'));

        $this->assertSame('facebook', User::where('username', 'fbuser')->value('signup_source'));

        $this->flushSession();
        $this->withHeader('User-Agent', self::PHONE)->get('/?ref=whatsapp');

        $this->flushSession();
        $this->actingAs($this->admin())->get('/admin?traffic=today')
            ->assertOk()
            ->assertSee('Visitors and where they come from')
            ->assertSee('Registered customers')
            ->assertSee('Facebook')
            ->assertSee('WhatsApp')
            ->assertSee('/?ref=telegram', false);

        $this->actingAs($this->admin())->get(route('admin.customers.show', User::where('username', 'fbuser')->value('id')))
            ->assertSee('Signed up from Facebook');
    }
}
