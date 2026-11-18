<?php

/*
 * Admin > Server Tools. Change these in .env if the hosting paths are different.
 */
return [
    // false hides nothing but refuses every Run button (for a locked-down server).
    'enabled' => (bool) env('SERVER_TOOLS_ENABLED', true),

    // The PHP command-line program, used to run composer.phar. Empty = found automatically.
    // On cPanel it is usually /opt/cpanel/ea-php82/root/usr/bin/php.
    'php' => env('SERVER_TOOLS_PHP', ''),

    // "Copy public folders": only needed when the website is served from a different folder than
    // this project's public/ (e.g. /home/USER/public_html). Empty = the tool is off.
    'copy_to' => env('SERVER_TOOLS_COPY_TO', ''),

    // Which folders inside public/ are copied, comma separated.
    'copy_folders' => array_values(array_filter(array_map('trim', explode(',', (string) env('SERVER_TOOLS_COPY_FOLDERS', 'assets,uploads'))))),

    // Lines of storage/logs/laravel.log shown on the page.
    'log_lines' => (int) env('SERVER_TOOLS_LOG_LINES', 300),
];
