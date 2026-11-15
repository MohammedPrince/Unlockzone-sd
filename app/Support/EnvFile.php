<?php

namespace App\Support;

use RuntimeException;

/**
 * Changes keys in the .env file and leaves every other line as it is.
 * Values are always written in double quotes with \ " and $ escaped, so a password such as
 * p@ss"$word is stored exactly and never read as a ${VARIABLE}.
 */
class EnvFile
{
    public function __construct(private ?string $path = null)
    {
    }

    public function path(): string
    {
        return $this->path ?? app()->environmentFilePath();
    }

    /**
     * Sets each KEY => value. Existing keys are changed where they are; new keys are added
     * at the end under $section (a comment line).
     */
    public function set(array $values, string $section = ''): void
    {
        $path = $this->path();

        if (!is_file($path)) {
            throw new RuntimeException('The .env file was not found at ' . $path);
        }
        if (!is_writable($path)) {
            throw new RuntimeException('The .env file cannot be written. Give the web server write permission on ' . $path);
        }

        $text = (string) file_get_contents($path);
        $added = [];

        foreach ($values as $key => $value) {
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                throw new RuntimeException("Invalid .env key: {$key}");
            }

            $line = $key . '=' . self::quote((string) $value);
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

            if (preg_match($pattern, $text)) {
                $text = preg_replace_callback($pattern, fn () => $line, $text, 1);
            } else {
                $added[] = $line;
            }
        }

        if ($added) {
            $text = rtrim($text, "\r\n") . "\n\n" . ($section !== '' ? "# {$section}\n" : '') . implode("\n", $added) . "\n";
        }

        if (file_put_contents($path, $text, LOCK_EX) === false) {
            throw new RuntimeException('The .env file could not be saved.');
        }
    }

    /** "value" with \ " and $ escaped. Line breaks are refused: they would start a new key. */
    public static function quote(string $value): string
    {
        if (preg_match('/[\r\n\0]/', $value)) {
            throw new RuntimeException('A .env value cannot contain line breaks.');
        }

        return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
    }
}
