<?php

namespace App\Services;

use App\Repositories\ActivityLogRepository;
use App\Support\EnvFile;
use Illuminate\Support\Facades\Artisan;

/**
 * Admin > Server Tools > Email settings. Everything is stored in .env (MAIL_* keys),
 * never in the database. The password is never sent back to the browser.
 */
class MailSettingsService
{
    public const MAILERS = [
        'smtp' => 'SMTP (hosting email account)',
        'sendmail' => 'Sendmail (the server\'s own mail program)',
        'log' => 'Off: write emails to laravel.log only',
    ];

    public const ENCRYPTIONS = [
        'ssl' => 'SSL (usually port 465)',
        'tls' => 'TLS / STARTTLS (usually port 587)',
    ];

    public function __construct(private ActivityLogRepository $logs)
    {
    }

    /** What the form shows: the values the site is using now. */
    public function current(): array
    {
        $smtp = config('mail.mailers.smtp');
        $port = (int) ($smtp['port'] ?? 465);
        $scheme = $smtp['scheme'] ?? null;

        return [
            'admin_address' => (string) config('mail.admin_address', ''),
            'mailer' => array_key_exists((string) config('mail.default'), self::MAILERS) ? config('mail.default') : 'log',
            'host' => (string) ($smtp['host'] ?? ''),
            'port' => $port,
            'encryption' => $scheme === 'smtps' || ($scheme === null && $port === 465) ? 'ssl' : 'tls',
            'username' => (string) ($smtp['username'] ?? ''),
            'has_password' => (string) ($smtp['password'] ?? '') !== '',
            'from_address' => (string) config('mail.from.address', ''),
            'from_name' => (string) config('mail.from.name', ''),
        ];
    }

    /** Writes the settings to .env and makes the site use them on the next page. */
    public function save(array $data, int $adminId): void
    {
        $values = [
            'MAIL_ADMIN_ADDRESS' => implode(',', $this->addresses($data['admin_address'] ?? '')),
            'MAIL_MAILER' => $data['mailer'],
            'MAIL_SCHEME' => $data['encryption'] === 'ssl' ? 'smtps' : 'smtp',
            'MAIL_HOST' => trim((string) ($data['host'] ?? '')),
            'MAIL_PORT' => (string) ($data['port'] ?? ''),
            'MAIL_USERNAME' => trim((string) ($data['username'] ?? '')),
            'MAIL_FROM_ADDRESS' => trim($data['from_address']),
            'MAIL_FROM_NAME' => trim((string) ($data['from_name'] ?? '')) ?: (setting('site_name') ?: config('app.name')),
        ];

        // An empty password box keeps the saved password.
        if (($data['password'] ?? '') !== '') {
            $values['MAIL_PASSWORD'] = $data['password'];
        }

        (new EnvFile())->set($values, 'Email (Admin > Server Tools > Email settings)');

        // With a cached config the new .env would be ignored: build the cache again from it.
        if (app()->configurationIsCached()) {
            Artisan::call('config:cache');
        }

        // Never write the password to the log.
        $this->logs->log('server_tool', 'Saved email settings (' . $values['MAIL_MAILER'] . ', ' . ($values['MAIL_HOST'] ?: '—') . ')', null, [
            'tool' => 'mail-settings',
            'password_changed' => isset($values['MAIL_PASSWORD']),
        ], $adminId);
    }

    /** Valid addresses from a comma, semicolon or space separated list. */
    public function addresses(string $list): array
    {
        $parts = preg_split('/[\s,;]+/', $list, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter($parts, fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL))));
    }
}
