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
RUN composer install \
    --no-dev \
    --no-ansi \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# ---- Frontend assets (Webpack Encore) ----
FROM node:20-alpine AS assets
WORKDIR /app

COPY --from=vendor /app /app
RUN npm ci && npm run build

# ---- Production image (PHP-FPM + Nginx) ----
FROM php:8.2-fpm-alpine AS app

RUN apk add --no-cache \
    nginx \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        opcache \
        pdo_mysql \
        zip \
    && rm -rf /var/cache/apk/*

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
RUN chmod +x /entrypoint.sh

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    PORT=8080

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
