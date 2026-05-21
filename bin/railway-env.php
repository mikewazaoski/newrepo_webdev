#!/usr/bin/env php
<?php

/**
 * Resolves Railway MySQL variables into a Symfony-ready DATABASE_URL and writes /app/.env.
 * Run from entrypoint.sh before cache warmup and migrations.
 */

declare(strict_types=1);

function env(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function resolveDatabaseUrl(): ?string
{
    foreach (['DATABASE_URL', 'MYSQL_URL', 'MYSQL_PUBLIC_URL'] as $name) {
        $url = env($name);
        if ($url !== null) {
            return $url;
        }
    }

    $host = env('MYSQLHOST');
    $database = env('MYSQLDATABASE');
    $user = env('MYSQLUSER');
    $password = env('MYSQLPASSWORD');

    if ($host === null || $database === null || $user === null || $password === null) {
        return null;
    }

    $port = env('MYSQLPORT', '3306');

    return sprintf(
        'mysql://%s:%s@%s:%s/%s',
        rawurlencode($user),
        rawurlencode($password),
        $host,
        $port,
        $database
    );
}

function normalizeDatabaseUrl(string $url): string
{
    $parts = parse_url($url);
    if ($parts === false) {
        return $url;
    }

    $query = [];
    if (isset($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $query += ['serverVersion' => '8.0', 'charset' => 'utf8mb4'];

    $parts['query'] = http_build_query($query);

    $scheme = $parts['scheme'] ?? 'mysql';
    $user = $parts['user'] ?? '';
    $pass = isset($parts['pass']) ? ':'.$parts['pass'] : '';
    $auth = ($user !== '' || $pass !== '') ? $user.$pass.'@' : '';
    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':'.$parts['port'] : '';
    $path = $parts['path'] ?? '';
    $queryString = '?'.$parts['query'];

    $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

    return sprintf('%s://%s%s%s%s%s%s', $scheme, $auth, $host, $port, $path, $queryString, $fragment);
}

function dotenvQuote(string $value): string
{
    if (preg_match('/[\s#"\'\\\\]/', $value)) {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    return $value;
}

function writeLine(array &$lines, string $key, ?string $value): void
{
    if ($value === null || $value === '') {
        return;
    }

    $lines[] = $key.'='.dotenvQuote($value);
}

$projectDir = dirname(__DIR__);
$envPath = $projectDir.'/.env';

$lines = [];

writeLine($lines, 'APP_ENV', env('APP_ENV', 'prod'));
writeLine($lines, 'APP_DEBUG', env('APP_DEBUG', '0'));
writeLine($lines, 'APP_SECRET', env('APP_SECRET'));
writeLine($lines, 'TRUSTED_PROXIES', env('TRUSTED_PROXIES', 'REMOTE_ADDR'));
writeLine($lines, 'DEFAULT_URI', env('DEFAULT_URI'));
writeLine($lines, 'MESSENGER_TRANSPORT_DSN', env('MESSENGER_TRANSPORT_DSN', 'doctrine://default?auto_setup=0'));
writeLine($lines, 'MAILER_DSN', env('MAILER_DSN', 'null://null'));
writeLine($lines, 'CORS_ALLOW_ORIGIN', env('CORS_ALLOW_ORIGIN'));
writeLine($lines, 'GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID'));
writeLine($lines, 'GOOGLE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET'));

$databaseUrl = resolveDatabaseUrl();
if ($databaseUrl !== null) {
    $databaseUrl = normalizeDatabaseUrl($databaseUrl);
    putenv('DATABASE_URL='.$databaseUrl);
    $_ENV['DATABASE_URL'] = $databaseUrl;
    $_SERVER['DATABASE_URL'] = $databaseUrl;
    writeLine($lines, 'DATABASE_URL', $databaseUrl);
}

file_put_contents($envPath, implode("\n", $lines)."\n");

$exportKeys = [
    'APP_ENV', 'APP_DEBUG', 'APP_SECRET', 'TRUSTED_PROXIES', 'DEFAULT_URI',
    'DATABASE_URL', 'MESSENGER_TRANSPORT_DSN', 'MAILER_DSN', 'CORS_ALLOW_ORIGIN',
    'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET',
];

if (in_array('--shell', $argv, true)) {
    foreach ($exportKeys as $key) {
        $value = env($key);
        if ($value !== null) {
            echo 'export '.$key.'='.escapeshellarg($value)."\n";
        }
    }
    exit(0);
}

if ($databaseUrl === null) {
    fwrite(STDERR, "railway-env: WARNING — no database URL found. Set DATABASE_URL=\${{MySQL.MYSQL_URL}} on the app service.\n");
    exit(0);
}

fwrite(STDOUT, "railway-env: wrote .env with DATABASE_URL for ".(parse_url($databaseUrl, PHP_URL_HOST) ?: 'database')."\n");
