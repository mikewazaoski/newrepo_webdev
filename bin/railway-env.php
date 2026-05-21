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

function isLocalDockerDatabaseUrl(string $url): bool
{
    $host = parse_url($url, PHP_URL_HOST);
    if ($host === false || $host === null || $host === '') {
        return false;
    }

    return in_array(strtolower($host), ['127.0.0.1', 'localhost', 'mysql'], true);
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

function writeRequired(array &$lines, string $key, string $value): void
{
    $lines[] = $key.'='.dotenvQuote($value);
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

function resolveDefaultUri(): string
{
    $uri = env('DEFAULT_URI');
    if ($uri !== null) {
        return rtrim($uri, '/');
    }

    foreach (['RAILWAY_STATIC_URL', 'RAILWAY_PUBLIC_URL'] as $name) {
        $url = env($name);
        if ($url !== null) {
            return rtrim($url, '/');
        }
    }

    $domain = env('RAILWAY_PUBLIC_DOMAIN');
    if ($domain !== null) {
        return 'https://'.$domain;
    }

    foreach ($_ENV + $_SERVER as $key => $value) {
        if (
            is_string($key)
            && is_string($value)
            && $value !== ''
            && str_starts_with($key, 'RAILWAY_SERVICE_')
            && str_ends_with($key, '_URL')
        ) {
            return rtrim($value, '/');
        }
    }

    fwrite(STDERR, "railway-env: WARNING — DEFAULT_URI not set; using http://localhost (set DEFAULT_URI to your Railway HTTPS URL).\n");

    return 'http://localhost';
}

function resolveAppSecret(): string
{
    $secret = env('APP_SECRET');
    if ($secret !== null) {
        return $secret;
    }

    $generated = bin2hex(random_bytes(32));
    fwrite(STDERR, "railway-env: WARNING — APP_SECRET not set; generated an ephemeral secret (set APP_SECRET in Railway Variables).\n");

    return $generated;
}

$projectDir = dirname(__DIR__);
$envPath = $projectDir.'/.env';

$lines = [];

$appEnv = env('APP_ENV', 'prod');
$appDebug = env('APP_DEBUG', '0');
$appSecret = resolveAppSecret();
$defaultUri = resolveDefaultUri();
$corsOrigin = env('CORS_ALLOW_ORIGIN', "^https?://(localhost|127\\.0\\.0\\.1|.*\\.up\\.railway\\.app)(:[0-9]+)?$");
$googleClientId = env('GOOGLE_CLIENT_ID', '');
$googleClientSecret = env('GOOGLE_CLIENT_SECRET', '');

writeRequired($lines, 'APP_ENV', $appEnv);
writeRequired($lines, 'APP_DEBUG', $appDebug);
writeRequired($lines, 'APP_SECRET', $appSecret);
writeRequired($lines, 'TRUSTED_PROXIES', env('TRUSTED_PROXIES', 'REMOTE_ADDR'));
writeRequired($lines, 'DEFAULT_URI', $defaultUri);
writeRequired($lines, 'MESSENGER_TRANSPORT_DSN', env('MESSENGER_TRANSPORT_DSN', 'doctrine://default?auto_setup=0'));
writeRequired($lines, 'MAILER_DSN', env('MAILER_DSN', 'null://null'));
writeRequired($lines, 'CORS_ALLOW_ORIGIN', $corsOrigin);
writeRequired($lines, 'GOOGLE_CLIENT_ID', $googleClientId);
writeRequired($lines, 'GOOGLE_CLIENT_SECRET', $googleClientSecret);

$databaseUrl = resolveDatabaseUrl();
if ($databaseUrl !== null && isLocalDockerDatabaseUrl($databaseUrl)) {
    fwrite(STDERR, "railway-env: WARNING — DATABASE_URL uses a local/Docker host ({$databaseUrl}).\n");
    fwrite(STDERR, "  On Railway: delete DATABASE_URL and set DATABASE_URL=\${{MySQL.MYSQL_URL}} after adding MySQL.\n");
    $databaseUrl = null;
    putenv('DATABASE_URL');
    unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);
}

if ($databaseUrl !== null) {
    $databaseUrl = normalizeDatabaseUrl($databaseUrl);
    writeRequired($lines, 'DATABASE_URL', $databaseUrl);
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
