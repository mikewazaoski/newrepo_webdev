#!/usr/bin/env php
<?php

/**
 * Writes PHP-FPM env[] directives so workers always receive DATABASE_URL and other vars.
 */

declare(strict_types=1);

$vars = [
    'APP_ENV',
    'APP_DEBUG',
    'APP_SECRET',
    'TRUSTED_PROXIES',
    'DEFAULT_URI',
    'DATABASE_URL',
    'MESSENGER_TRANSPORT_DSN',
    'MAILER_DSN',
    'CORS_ALLOW_ORIGIN',
];

$lines = ["[www]"];

foreach ($vars as $name) {
    $value = getenv($name);
    if ($value === false || $value === '') {
        continue;
    }

    $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    $lines[] = 'env['.$name.'] = "'.$escaped.'"';
}

$target = '/usr/local/etc/php-fpm.d/zz-app-env.conf';
file_put_contents($target, implode("\n", $lines)."\n");

$host = getenv('DATABASE_URL') ? (parse_url(getenv('DATABASE_URL'), PHP_URL_HOST) ?: 'unknown') : 'none';
fwrite(STDOUT, "write-fpm-env: wrote {$target} (DATABASE_URL host: {$host})\n");
