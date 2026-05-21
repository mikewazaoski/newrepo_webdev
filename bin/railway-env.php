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

function resolveDatabaseUrlFromMysqlVars(): ?string
{
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

function resolveDatabaseUrl(): ?string
{
    $onRailway = env('RAILWAY_ENVIRONMENT') !== null
        || env('RAILWAY_PROJECT_ID') !== null
        || env('RAILWAY_PUBLIC_DOMAIN') !== null;

    if ($onRailway) {
        foreach (['MYSQL_PRIVATE_URL', 'DATABASE_URL', 'MYSQL_URL'] as $name) {
            $url = env($name);
            if ($url !== null && !isLocalDockerDatabaseUrl($url)) {
                return $url;
            }
            if ($url !== null && isLocalDockerDatabaseUrl($url)) {
                fwrite(STDERR, "railway-env: ignoring {$name} with Docker-only host; trying other sources.\n");
            }
        }

        $fromMysqlVars = resolveDatabaseUrlFromMysqlVars();
        if ($fromMysqlVars !== null) {
            return $fromMysqlVars;
        }

        $publicUrl = env('MYSQL_PUBLIC_URL');
        if ($publicUrl !== null && !isLocalDockerDatabaseUrl($publicUrl)) {
            return $publicUrl;
        }

        return null;
    }

    foreach (['MYSQL_PRIVATE_URL', 'DATABASE_URL', 'MYSQL_URL', 'MYSQL_PUBLIC_URL'] as $name) {
        $url = env($name);
        if ($url !== null && !isLocalDockerDatabaseUrl($url)) {
            return $url;
        }
        if ($url !== null && isLocalDockerDatabaseUrl($url)) {
            fwrite(STDERR, "railway-env: ignoring {$name} with Docker-only host; trying other sources.\n");
        }
    }

    return resolveDatabaseUrlFromMysqlVars();
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

    $query += ['serverVersion' => '8.0.31', 'charset' => 'utf8mb4'];

    $host = $parts['host'] ?? '';
    if (
        is_string($host)
        && $host !== ''
        && !str_ends_with($host, '.railway.internal')
        && (str_contains($host, 'railway') || str_contains($host, 'rlwy.net'))
        && !isset($query['ssl-mode'])
    ) {
        // Public Railway MySQL proxy requires SSL from PHP PDO
        $query['ssl-mode'] = 'REQUIRED';
    }

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
    // Symfony interprets % as parameter syntax in .env files — always escape it.
    $value = str_replace('%', '%%', $value);

    if (preg_match('/[\s#"\'\\\\=:&?@]/', $value)) {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    return $value;
}

function resolveAppSecret(bool $onRailway): ?string
{
    $secret = env('APP_SECRET');
    if ($secret !== null) {
        return $secret;
    }

    if (!$onRailway) {
        return null;
    }

    $seed = env('RAILWAY_PROJECT_ID')
        ?? env('RAILWAY_ENVIRONMENT_NAME')
        ?? env('RAILWAY_PUBLIC_DOMAIN')
        ?? 'pet-pantry-railway';

    return hash('sha256', 'pet-pantry-app-secret:'.$seed);
}

function resolveDefaultUri(): ?string
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

    return null;
}

function writeLine(array &$lines, string $key, ?string $value): void
{
    if ($value === null || $value === '') {
        return;
    }

    $lines[] = $key.'='.dotenvQuote($value);
}

function applyEnv(string $key, ?string $value): void
{
    if ($value === null || $value === '') {
        return;
    }

    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

function buildResolvedEnv(): array
{
    $onRailway = env('RAILWAY_ENVIRONMENT') !== null
        || env('RAILWAY_PROJECT_ID') !== null
        || env('RAILWAY_PUBLIC_DOMAIN') !== null;

    $resolved = [
        'APP_ENV' => env('APP_ENV', 'prod'),
        'APP_DEBUG' => env('APP_DEBUG', '0'),
        'APP_SECRET' => resolveAppSecret($onRailway),
        'TRUSTED_PROXIES' => env('TRUSTED_PROXIES', 'REMOTE_ADDR'),
        'DEFAULT_URI' => resolveDefaultUri(),
        'MESSENGER_TRANSPORT_DSN' => env('MESSENGER_TRANSPORT_DSN', $onRailway ? 'sync://' : 'doctrine://default?auto_setup=0'),
        'MAILER_DSN' => env('MAILER_DSN', 'null://null'),
        'CORS_ALLOW_ORIGIN' => env('CORS_ALLOW_ORIGIN', $onRailway ? '*' : null),
        'GOOGLE_CLIENT_ID' => env('GOOGLE_CLIENT_ID'),
        'GOOGLE_CLIENT_SECRET' => env('GOOGLE_CLIENT_SECRET'),
    ];

    $databaseUrl = resolveDatabaseUrl();
    if ($databaseUrl !== null) {
        $resolved['DATABASE_URL'] = normalizeDatabaseUrl($databaseUrl);
    }

    return array_filter(
        $resolved,
        static fn (?string $value): bool => $value !== null && $value !== ''
    );
}

$projectDir = dirname(__DIR__);
$envPath = $projectDir.'/.env';

$resolvedEnv = buildResolvedEnv();

$lines = [];
foreach ($resolvedEnv as $key => $value) {
    writeLine($lines, $key, $value);
    applyEnv($key, $value);
}

file_put_contents($envPath, implode("\n", $lines)."\n");

$envLocalPhpPath = $projectDir.'/.env.local.php';
$phpEnv = $resolvedEnv;
if (isset($phpEnv['APP_DEBUG'])) {
    $phpEnv['APP_DEBUG'] = (int) $phpEnv['APP_DEBUG'];
}
file_put_contents(
    $envLocalPhpPath,
    "<?php\n\nreturn ".var_export($phpEnv, true).";\n"
);

$exportKeys = [
    'APP_ENV', 'APP_DEBUG', 'APP_SECRET', 'TRUSTED_PROXIES', 'DEFAULT_URI',
    'DATABASE_URL', 'MESSENGER_TRANSPORT_DSN', 'MAILER_DSN', 'CORS_ALLOW_ORIGIN',
    'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET',
];

if (in_array('--shell', $argv, true)) {
    $rawDatabaseUrl = getenv('DATABASE_URL');
    if (is_string($rawDatabaseUrl) && $rawDatabaseUrl !== '' && isLocalDockerDatabaseUrl($rawDatabaseUrl)) {
        echo "unset DATABASE_URL\n";
    }

    foreach ($exportKeys as $key) {
        $value = $resolvedEnv[$key] ?? null;
        if ($value !== null) {
            echo 'export '.$key.'='.escapeshellarg($value)."\n";
        }
    }
    exit(0);
}

if (!isset($resolvedEnv['DATABASE_URL'])) {
    fwrite(STDERR, "railway-env: WARNING — no database URL found. Set DATABASE_URL=\${{MySQL.MYSQL_URL}} on the app service.\n");
    exit(0);
}

$databaseUrl = $resolvedEnv['DATABASE_URL'];
fwrite(STDOUT, "railway-env: wrote .env with DATABASE_URL for ".(parse_url($databaseUrl, PHP_URL_HOST) ?: 'database')."\n");
