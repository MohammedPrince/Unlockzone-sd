<?php

namespace App\Services;

use App\Repositories\ActivityLogRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
     * key => [title, command shown on the card, what it does, danger (asks first, red button), why it is off (null = available)]
     */
    public function tools(): array
    {
        $tools = [
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
            'copy-public' => ['Copy public folders', 'copy public/{' . implode(',', $this->copyFolders()) . '} → ' . ($this->copyTarget() ?: 'SERVER_TOOLS_COPY_TO'), 'Copies the CSS, JS, images and uploads from the project\'s public folder to the folder the website is served from. Only needed when that is a different folder (e.g. public_html).', true],
        ];

        $off = $this->copyTarget() === '' ? 'Off: the site is served straight from public/. Set SERVER_TOOLS_COPY_TO in .env to use it.' : null;

        return array_map(fn ($tool) => [...$tool, null], array_diff_key($tools, ['copy-public' => 1])) + ['copy-public' => [...$tools['copy-public'], $off]];
    }

    public function enabled(): bool
    {
        return (bool) config('server_tools.enabled', true);
    }

    /**
     * Runs one tool and returns [ok, output]. The run is written to the activity log.
     */
    public function run(string $key, int $adminId): array
    {
        if (!array_key_exists($key, $this->tools())) {
            throw new RuntimeException('Unknown tool.');
        }
        if (!$this->enabled()) {
            throw new RuntimeException('Server Tools are switched off (SERVER_TOOLS_ENABLED=false).');
        }
        if ($this->tools()[$key][4] !== null) {
            return [false, $this->tools()[$key][4], 0.0];
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
                'copy-public' => $this->copyPublic(),
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
            ? [$this->phpBinary(), $phar]
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

    /**
     * The PHP command-line program. Under PHP-FPM (most hosting) PHP_BINARY is php-fpm, which cannot
     * run composer.phar, so look for the plain "php" next to it.
     */
    public function phpBinary(): string
    {
        $configured = trim((string) config('server_tools.php'));
        if ($configured !== '') {
            return $configured;
        }

        $name = strtolower(basename(PHP_BINARY));
        if (PHP_BINARY !== '' && !str_contains($name, 'fpm') && !str_contains($name, 'cgi') && !str_contains($name, 'httpd')) {
            return PHP_BINARY;
        }

        foreach ([PHP_BINDIR . DIRECTORY_SEPARATOR . 'php', PHP_BINDIR . DIRECTORY_SEPARATOR . 'php.exe'] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return (new ExecutableFinder())->find('php') ?? 'php';
    }

    public function copyTarget(): string
    {
        return rtrim(trim((string) config('server_tools.copy_to')), '/\\');
    }

    /** Folder names only (no slashes or dots), so nothing outside public/ can be named. */
    public function copyFolders(): array
    {
        return array_values(array_filter((array) config('server_tools.copy_folders', []), fn ($f) => is_string($f) && preg_match('/^[A-Za-z0-9_-]+$/', $f)));
    }

    /** Copies the listed public/ folders to SERVER_TOOLS_COPY_TO. */
    private function copyPublic(): string
    {
        $target = $this->copyTarget();
        $real = $target !== '' ? realpath($target) : false;

        if ($real === false || !is_dir($real)) {
            throw new RuntimeException("The copy target folder does not exist: {$target}");
        }
        if (!is_writable($real)) {
            throw new RuntimeException("The copy target folder is not writable: {$real}");
        }
        // Never copy onto the project itself (that would overwrite or loop).
        $base = realpath(base_path());
        if ($real === realpath(public_path()) || $real === $base || str_starts_with($real . DIRECTORY_SEPARATOR, $base . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The copy target must be outside the project folder.');
        }

        $lines = [];
        foreach ($this->copyFolders() as $folder) {
            $from = public_path($folder);
            if (!is_dir($from)) {
                $lines[] = "skipped {$folder}/ (not found in public/)";
                continue;
            }

            $files = iterator_count(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS)));
            if (!File::copyDirectory($from, $real . DIRECTORY_SEPARATOR . $folder)) {
                throw new RuntimeException("Could not copy {$folder}/ to {$real}.");
            }
            $lines[] = "copied {$folder}/ ({$files} files)";
        }

        return 'Copied to ' . $real . "\n" . implode("\n", $lines);
    }

    /** The strip at the top of the page: which site this is and whether the tools are on. */
    public function summary(): array
    {
        return [
            'stage' => app()->environment('production') ? 'PROD' : 'DEV',
            'address' => rtrim((string) config('app.url'), '/'),
            'folder' => basename(base_path()),
            'enabled' => $this->enabled(),
        ];
    }

    /** Paths the tools use; each can be changed in .env (SERVER_TOOLS_* keys). */
    public function details(): array
    {
        $target = $this->copyTarget();

        return [
            'Status' => ($this->enabled() ? 'On' : 'Off (SERVER_TOOLS_ENABLED=false)') . ' (' . (app()->environment('production') ? 'hosting' : 'local') . ')',
            'Project folder' => base_path(),
            'PHP binary' => $this->phpBinary(),
            'Composer' => is_file(base_path('composer.phar')) ? base_path('composer.phar') : ((new ExecutableFinder())->find('composer') ?? 'Not found: upload composer.phar to the project folder'),
            'Copy from' => public_path(),
            'Copy to' => $target !== '' ? $target : 'Off (the site is served straight from public/)',
            'Folders copied' => implode(', ', $this->copyFolders()) ?: '—',
            'Backups folder' => storage_path(self::BACKUP_DIR),
            'Log file' => $this->logPath(),
        ];
    }

    /* ---------- laravel.log viewer ---------- */

    public function logPath(): string
    {
        return storage_path('logs' . DIRECTORY_SEPARATOR . 'laravel.log');
    }

    /** Size and last change of the log, or null when there is no log yet. */
    public function logInfo(): ?array
    {
        $path = $this->logPath();
        if (!is_file($path)) {
            return null;
        }

        return ['size' => $this->size((int) filesize($path)), 'updated' => \Illuminate\Support\Carbon::createFromTimestamp(filemtime($path))];
    }

    /**
     * The last lines of the log as entries, newest first. Each entry keeps its stack-trace lines and
     * its level (error, warning, info...) so errors can be shown in red.
     *
     * @return array<int, array{level: string, text: string}>
     */
    public function logEntries(?int $lines = null): array
    {
        $path = $this->logPath();
        if (!is_file($path) || filesize($path) === 0) {
            return [];
        }

        $lines = max(20, min(2000, $lines ?? (int) config('server_tools.log_lines', 300)));

        // Read only the end of the file: a log can be hundreds of MB.
        $handle = fopen($path, 'rb');
        $size = filesize($path);
        $chunk = min($size, 768 * 1024);
        fseek($handle, $size - $chunk);
        $text = fread($handle, $chunk);
        fclose($handle);

        $rows = preg_split('/\r\n|\n|\r/', rtrim((string) $text));
        if ($chunk < $size) {
            array_shift($rows); // the first line was cut in the middle
        }
        $rows = array_slice($rows, -$lines);

        $entries = [];
        foreach ($rows as $row) {
            $row = mb_strimwidth($row, 0, 3000, ' …');
            if (preg_match('/^\[\d{4}-\d{2}-\d{2}[ T][^\]]*\]\s+[\w-]+\.([A-Z]+):/', $row, $m)) {
                $entries[] = ['level' => strtolower($m[1]), 'text' => $row];
            } elseif ($entries) {
                $entries[count($entries) - 1]['text'] .= "\n" . $row;
            } else {
                $entries[] = ['level' => 'trace', 'text' => $row];
            }
        }

        return array_reverse($entries);
    }

    public function clearLog(int $adminId): void
    {
        $path = $this->logPath();
        if (is_file($path)) {
            file_put_contents($path, '');
        }

        $this->logs->log('server_tool', 'Cleared laravel.log', null, ['tool' => 'log-clear'], $adminId);
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
