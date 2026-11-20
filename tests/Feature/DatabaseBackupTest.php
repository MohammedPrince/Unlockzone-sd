<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ServerToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Weekly `php artisan db:backup`: the same backup as Server Tools > Database backup, listed there and
 * downloadable at /admin/tools/backups/backup-YYYY-MM-DD-HHMMSS.sql.
 */
class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /** @var list<string> */
    private array $made = [];

    protected function tearDown(): void
    {
        foreach ($this->made as $name) {
            if ($path = app(ServerToolService::class)->backupPath($name)) {
                @unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_the_weekly_command_saves_a_backup_admins_download_from_the_tools_address(): void
    {
        // Sunday 00:00 in Khartoum (UTC+2) = Saturday 22:00 UTC: the file name reads the local midnight.
        $this->travelTo(Carbon::parse('2026-11-21 22:00:05', 'UTC'));
        $this->artisan('db:backup')->assertSuccessful();

        $name = 'backup-2026-11-22-000005.sql';
        $this->made[] = $name;
        $path = app(ServerToolService::class)->backupPath($name);
        $this->assertNotNull($path, 'backup file named on the local clock');

        $sql = file_get_contents($path);
        $this->assertStringContainsString('CREATE TABLE `tbl_users`', $sql);
        $this->assertStringContainsString("'Haafedk test 1'", $sql);
        $this->assertStringEndsWith("-- End of backup\n", $sql);
        $this->assertDatabaseHas('activity_logs', ['action' => 'server_tool', 'description' => 'Weekly database backup']);

        // Admins: listed on Server Tools and downloaded at /admin/tools/backups/{file}, on whatever site this is.
        $admin = User::where('role', 'admin')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.tools.index'))->assertOk()
            ->assertSee($name)->assertSee('once a week, every Sunday at 00:00 (Africa/Khartoum)');
        $this->assertSame(url('/admin/tools/backups/' . $name), route('admin.tools.backups.download', $name));
        $this->actingAs($admin)->get('/admin/tools/backups/' . $name)->assertOk()->assertDownload($name);
        $this->actingAs($admin)->get('/admin/tools/backups')->assertRedirect(route('admin.tools.index') . '#backups');

        // Nobody else.
        auth()->logout();
        $this->get('/admin/tools/backups/' . $name)->assertRedirect(route('admin.login'));
    }

    public function test_it_is_scheduled_once_a_week_at_midnight(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'db:backup'));

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * 0', $event->expression);
        $this->assertSame('Africa/Khartoum', $event->timezone);
    }
}
