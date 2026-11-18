<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Server Tools: site summary, server details, copying public folders and the laravel.log viewer. */
class ServerToolsPageTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private string $log;
    private ?string $savedLog = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Work on a copy of the real log so the developer's laravel.log is put back afterwards.
        $this->log = storage_path('logs/laravel.log');
        $this->savedLog = is_file($this->log) ? file_get_contents($this->log) : null;
    }

    protected function tearDown(): void
    {
        if ($this->savedLog !== null) {
            file_put_contents($this->log, $this->savedLog);
        }

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_page_shows_summary_details_and_log_newest_first_with_errors_marked(): void
    {
        file_put_contents($this->log, implode("\n", [
            '[2026-11-14 10:00:00] local.INFO: first message',
            '[2026-11-14 10:05:00] local.ERROR: something broke {"exception":"x"}',
            '#0 /app/Foo.php(12): bar()',
            '[2026-11-14 10:09:00] local.INFO: last message',
        ]) . "\n");

        $html = $this->actingAs($this->admin())->get(route('admin.tools.index'))
            ->assertOk()
            ->assertSee('DEV')
            ->assertSee('Server details')
            ->assertSee('PHP binary')
            ->assertSee('Copy public folders')
            ->assertSee('Off: the site is served straight from public/.', false)
            ->assertSee('class="log-error"', false)
            ->getContent();

        // Newest first, and the stack-trace line stays with its error.
        $this->assertLessThan(strpos($html, 'first message'), strpos($html, 'last message'));
        $this->assertMatchesRegularExpression('/something broke.*\n#0 \/app\/Foo\.php/', html_entity_decode($html));
    }

    public function test_log_can_be_downloaded_and_cleared(): void
    {
        file_put_contents($this->log, "[2026-11-14 10:00:00] local.ERROR: boom\n");
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.tools.log.download'))->assertOk()->assertDownload();
        $this->actingAs($admin)->delete(route('admin.tools.log.clear'))->assertRedirect();
        $this->assertSame('', file_get_contents($this->log));
        $this->actingAs($admin)->get(route('admin.tools.index'))->assertSee('The log is empty');

        // Signed-out visitors cannot read or clear it.
        auth()->logout();
        $this->get(route('admin.tools.log.download'))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.tools.log.clear'))->assertRedirect(route('admin.login'));
    }

    public function test_copy_public_is_off_without_target_and_copies_when_set(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.tools.run', 'copy-public'))
            ->assertSessionHas('tool_result', fn ($r) => $r['ok'] === false);

        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'st-copy-' . uniqid();
        mkdir($target);
        config(['server_tools.copy_to' => $target, 'server_tools.copy_folders' => ['assets', '../secret']]);

        try {
            $this->actingAs($admin)->post(route('admin.tools.run', 'copy-public'))
                ->assertSessionHas('tool_result', fn ($r) => $r['ok'] === true);
            $this->assertFileExists($target . '/assets/css/app.css');
            $this->assertDirectoryDoesNotExist($target . '/secret');

            // Never onto the project itself.
            config(['server_tools.copy_to' => public_path()]);
            $this->actingAs($admin)->post(route('admin.tools.run', 'copy-public'))
                ->assertSessionHas('tool_result', fn ($r) => $r['ok'] === false && str_contains($r['output'], 'outside the project'));
        } finally {
            File::deleteDirectory($target);
        }
    }

    public function test_switched_off_tools_refuse_to_run(): void
    {
        config(['server_tools.enabled' => false]);

        $this->actingAs($this->admin())->get(route('admin.tools.index'))->assertOk()->assertSee('Server Tools are switched off');
        $this->actingAs($this->admin())->post(route('admin.tools.run', 'view-clear'))->assertForbidden();
    }
}
