<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

Request::setTrustedProxies(
    proxies: ['0.0.0.0/0'],
    trustedHeaderSet: Request::HEADER_X_FORWARDED_FOR
    | Request::HEADER_X_FORWARDED_HOST
    | Request::HEADER_X_FORWARDED_PORT
    | Request::HEADER_X_FORWARDED_PROTO
);

return function (array $context) {
    $env = $context['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'prod';
    $debug = (bool) ($context['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? false);

    return new Kernel($env, $debug);
};
