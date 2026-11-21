<?php

namespace Tests\Feature;

use App\Repositories\SettingRepository;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin > Settings > Home page: the Google Search Console verification tag on the home page. */
class GoogleSiteVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_code_is_taken_from_a_pasted_tag_or_plain_code(): void
    {
        $this->assertSame('5YDcEquz9_Sp-Vd', SettingService::verificationCode('<meta name="google-site-verification" content="5YDcEquz9_Sp-Vd" />'));
        $this->assertSame('abc123', SettingService::verificationCode('  abc123 '));
        $this->assertSame('abcscriptx', SettingService::verificationCode('abc"><script>x'));
        $this->assertSame('', SettingService::verificationCode(null));
    }

    public function test_home_page_shows_the_tag_only_when_set(): void
    {
        $this->get('/')->assertOk()->assertDontSee('google-site-verification', false);

        app(SettingRepository::class)->setMany(['google_site_verification' => 'abc123']);

        $this->get('/')->assertOk()
            ->assertSee('<meta name="google-site-verification" content="abc123">', false);
    }
}
