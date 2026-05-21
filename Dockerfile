# syntax=docker/dockerfile:1

# ---- Composer dependencies ----
FROM composer:2 AS vendor
WORKDIR /app

# Build-time only (override in Railway dashboard)
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    APP_SECRET=build_placeholder_set_in_railway \
    TRUSTED_PROXIES=REMOTE_ADDR \
    DATABASE_URL="sqlite:///:memory:"

COPY composer.json composer.lock symfony.lock ./
RUN composer install \
    --no-dev \
    --no-ansi \
    --no-interaction \
    --no-scripts \
    --prefer-dist

COPY . .

# Symfony post-install scripts need a readable .env (not shipped from host; see .dockerignore)
RUN printf '%s\n' \
    "APP_ENV=${APP_ENV}" \
    "APP_DEBUG=${APP_DEBUG}" \
    "APP_SECRET=${APP_SECRET}" \
    "DATABASE_URL=${DATABASE_URL}" \
    'TRUSTED_PROXIES=REMOTE_ADDR' \
    > .env

RUN composer install \
    --no-dev \
    --no-ansi \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ---- Frontend assets (Webpack Encore) ----
# Use bookworm (not alpine) to avoid stale BuildKit cache layers on Docker Desktop for Windows
FROM node:20-bookworm-slim AS assets
COPY --from=vendor /app /app
WORKDIR /app
RUN npm ci && npm run build

# ---- Production image (PHP-FPM + Nginx) ----
# Debian-based image: more reliable extension builds on Railway than Alpine apk
FROM php:8.2-fpm-bookworm AS app

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        nginx \
        libicu-dev \
        libzip-dev \
        zlib1g-dev \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        default-libmysqlclient-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        opcache \
        pdo_mysql \
        zip \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/* /tmp/* /usr/src/php*

# Opcache for production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app

COPY --from=assets /app /app

# Minimal .env for image build (composer/console); entrypoint.sh overwrites at runtime from Railway vars
RUN printf 'APP_ENV=prod\nAPP_DEBUG=0\n' > .env

RUN mkdir -p var/cache var/log public/uploads/images \
    && chown -R www-data:www-data var public/uploads

COPY docker/nginx/nginx-main.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/php-fpm/zz-railway.conf /usr/local/etc/php-fpm.d/zz-railway.conf
COPY entrypoint.sh /entrypoint.sh
# Fix Windows CRLF line endings so Linux can execute the script
RUN sed -i 's/\r$//' /entrypoint.sh && chmod +x /entrypoint.sh \
    && sed -i 's/\r$//' /app/bin/write-fpm-env.php && chmod +x /app/bin/write-fpm-env.php

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    PORT=80

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
