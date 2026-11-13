<?php

namespace App\Services;

use App\Repositories\ActivityLogRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Admin > Server Tools: a fixed list of maintenance commands, each run by its key.
 * Nothing typed by a user is ever executed; unknown keys are refused.
 */
class ServerToolService
{
    /** Where database backups are written (not reachable from the web). */
    public const BACKUP_DIR = 'app/private/backups';

    private const KEEP_BACKUPS = 15;

    public function __construct(private ActivityLogRepository $logs)
    {
    }

    /**
     * The tools, in the order they are shown.
     * key => [title, command shown on the card, what it does, danger (asks first, red button)]
     */
    public function tools(): array
    {
        return [
            'backup' => ['Database backup', 'full .sql export (built in)', 'Saves a full copy of the database as a .sql file you can download below. Do this first, before anything else.', false],
            'clear' => ['Clear caches', 'php artisan optimize:clear', 'Clears the config, route, view, event and app caches. Run it first after uploading new code or changing .env.', false],
            'optimize' => ['Optimize', 'php artisan optimize', 'Builds the config, route and view caches again so the site is fast. Run it after "Clear caches".', false],
            'config-clear' => ['Clear config cache', 'php artisan config:clear', 'Clears only the cached settings, so a changed .env is read again. Faster than "Clear caches".', false],
            'config-cache' => ['Cache config', 'php artisan config:cache', 'Caches the settings from .env and config/ again. Run it after "Clear config cache".', false],
            'view-clear' => ['Clear page templates', 'php artisan view:clear', 'Removes compiled pages so changed .blade.php files show at once.', false],
            'migrate' => ['Run migrations', 'php artisan migrate --force', 'Applies new database changes from the uploaded code. Take a database backup first.', true],
            'queue' => ['Run waiting jobs', 'php artisan queue:work --stop-when-empty', 'Runs the jobs waiting in the queue now, then stops. Use it when the queue cron is late or was down.', false],
            'storage-link' => ['Link storage folder', 'php artisan storage:link', 'Creates the public/storage link for uploaded files. Needed once on a new server.', false],
            'composer' => ['Composer install', $this->composerLabel(), 'Installs the PHP packages from composer.lock (the vendor folder). Run it when composer.json or composer.lock changed. Can take a few minutes.', true],
        ];
    }

    /**
     * Runs one tool and returns [ok, output]. The run is written to the activity log.
     */
    public function run(string $key, int $adminId): array
    {
        if (!array_key_exists($key, $this->tools())) {
            throw new RuntimeException('Unknown tool.');
        }

        @set_time_limit(600);
        $started = microtime(true);

        try {
            $output = match ($key) {
                'backup' => $this->backup(),
                'clear' => $this->artisan('optimize:clear'),
                'optimize' => $this->artisan('optimize'),
                'config-clear' => $this->artisan('config:clear'),
                'config-cache' => $this->artisan('config:cache'),
                'view-clear' => $this->artisan('view:clear'),
                'migrate' => $this->artisan('migrate', ['--force' => true]),
                'queue' => $this->artisan('queue:work', ['--stop-when-empty' => true, '--max-time' => 120, '--tries' => 3]),
                'storage-link' => $this->artisan('storage:link'),
                'composer' => $this->composer(),
            };
            $ok = true;
        } catch (Throwable $e) {
            report($e);
            $output = $e->getMessage();
            $ok = false;
        }

        $seconds = round(microtime(true) - $started, 1);
        $output = trim((string) $output) ?: 'Done. (No output.)';

        $this->logs->log('server_tool', ($ok ? 'Ran' : 'Failed to run') . " server tool: {$this->tools()[$key][0]}", null, [
            'tool' => $key, 'ok' => $ok, 'seconds' => $seconds,
        ], $adminId);

        return [$ok, mb_substr($output, -20000), $seconds];
    }

    private function artisan(string $command, array $options = []): string
    {
        $code = Artisan::call($command, $options);
        $output = Artisan::output();

        if ($code !== 0) {
            throw new RuntimeException(trim($output) ?: "The command ended with code {$code}.");
        }

        return $output;
    }

    /** composer install; --no-dev only in production so local test tools are kept. */
    private function composerArgs(): array
    {
        $args = ['install', '--no-interaction', '--optimize-autoloader'];

        return app()->environment('production') ? [...$args, '--no-dev'] : $args;
    }

    private function composerLabel(): string
    {
        return 'composer ' . implode(' ', $this->composerArgs());
    }

    private function composer(): string
    {
        // Prefer a composer.phar uploaded next to the project, then a composer installed on the server.
        $phar = base_path('composer.phar');
        $command = is_file($phar)
            ? [PHP_BINARY, $phar]
            : [(new ExecutableFinder())->find('composer') ?? throw new RuntimeException('Composer was not found. Upload composer.phar to the project folder.')];

        $process = new Process([...$command, ...$this->composerArgs()], base_path(), ['COMPOSER_HOME' => storage_path('app/private/composer')]);
        $process->setTimeout(600);
        $process->run();

        $output = $process->getOutput() . $process->getErrorOutput();
        if (!$process->isSuccessful()) {
            throw new RuntimeException(trim($output) ?: 'Composer failed.');
        }

        return $output;
    }

    /**
     * Writes every table (structure and rows) to a .sql file, without needing mysqldump on the server.
     */
    private function backup(): string
    {
        $dir = storage_path(self::BACKUP_DIR);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create the backup folder.');
        }

        $file = $dir . DIRECTORY_SEPARATOR . 'backup-' . now()->format('Y-m-d-His') . '.sql';
        $pdo = DB::connection()->getPdo();
        $out = fopen($file, 'wb');

        try {
            fwrite($out, "-- " . setting('site_name') . " database backup\n-- " . now()->toDateTimeString() . "\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

            $tables = array_map(fn ($row) => array_values((array) $row)[0], DB::select('SHOW TABLES'));
            $rows = 0;

            foreach ($tables as $table) {
                $create = (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");
                fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n" . ($create['Create Table'] ?? array_values($create)[1]) . ";\n\n");

                $stmt = $pdo->query("SELECT * FROM `{$table}`");
                $batch = [];
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $batch[] = '(' . implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)) . ')';
                    $rows++;
                    if (count($batch) === 200) {
                        fwrite($out, "INSERT INTO `{$table}` VALUES\n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                }
                if ($batch) {
                    fwrite($out, "INSERT INTO `{$table}` VALUES\n" . implode(",\n", $batch) . ";\n");
                }
                fwrite($out, "\n");
            }

            fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
        } finally {
            fclose($out);
        }

        $this->pruneBackups();

        return sprintf("Backup saved: %s\n%d tables, %d rows, %s.", basename($file), count($tables), $rows, $this->size(filesize($file)));
    }

    /** Newest first: [name, size, date]. */
    public function backups(): array
    {
        $files = glob(storage_path(self::BACKUP_DIR) . DIRECTORY_SEPARATOR . 'backup-*.sql') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return array_map(fn ($f) => [
            'name' => basename($f),
            'size' => $this->size(filesize($f)),
            'date' => \Illuminate\Support\Carbon::createFromTimestamp(filemtime($f)),
        ], $files);
    }

    /** Full path of a backup, or null. Only plain backup-*.sql names inside the backup folder are accepted. */
    public function backupPath(string $name): ?string
    {
        if (!preg_match('/^backup-[0-9-]+\.sql$/', $name)) {
            return null;
        }

        $path = storage_path(self::BACKUP_DIR) . DIRECTORY_SEPARATOR . $name;

        return is_file($path) ? $path : null;
    }

    public function deleteBackup(string $name, int $adminId): bool
    {
        $path = $this->backupPath($name);
        if (!$path) {
            return false;
        }

        @unlink($path);
        $this->logs->log('server_tool', "Deleted database backup {$name}", null, ['tool' => 'backup-delete'], $adminId);

        return true;
    }

    private function pruneBackups(): void
    {
        foreach (array_slice($this->backups(), self::KEEP_BACKUPS) as $old) {
            @unlink(storage_path(self::BACKUP_DIR) . DIRECTORY_SEPARATOR . $old['name']);
        }
    }

    /** Facts shown at the top of the page. */
    public function info(): array
    {
        $count = function (string $table) {
            try {
                return DB::table($table)->count();
            } catch (Throwable) {
                return null;
            }
        };

        $free = @disk_free_space(base_path());

        return [
            'PHP' => PHP_VERSION,
            'Laravel' => app()->version(),
            'Environment' => app()->environment(),
            'Debug mode' => config('app.debug') ? 'On' : 'Off',
            'Config cached' => app()->configurationIsCached() ? 'Yes' : 'No',
            'Routes cached' => app()->routesAreCached() ? 'Yes' : 'No',
            'Queue' => config('queue.default'),
            'Waiting jobs' => $count('jobs'),
            'Failed jobs' => $count('failed_jobs'),
            'Free disk space' => $free !== false ? $this->size((int) $free) : '?',
        ];
    }

    private function size(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, $i ? 1 : 0) . ' ' . $units[$i];
    }
}
