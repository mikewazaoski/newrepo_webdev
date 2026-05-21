#!/bin/sh
set -e

PORT="${PORT:-80}"
export PORT

echo "Starting Pet Pantry on port ${PORT}..."

sed -i "s/__PORT__/${PORT}/g" /etc/nginx/conf.d/default.conf

cd /app

php bin/railway-env.php
eval "$(php bin/railway-env.php --shell)"
php bin/write-fpm-env.php
chmod 644 .env 2>/dev/null || true
chown www-data:www-data .env 2>/dev/null || true

if [ -n "${DATABASE_URL:-}" ]; then
    DB_HOST=$(php -r 'echo parse_url(getenv("DATABASE_URL"), PHP_URL_HOST) ?: "unknown";')
    echo "Database target host: ${DB_HOST}"
else
    echo "WARNING: DATABASE_URL is empty — set DATABASE_URL=\${{MySQL.MYSQL_URL}} on Railway."
fi

if [ -z "${APP_SECRET:-}" ]; then
    echo "WARNING: APP_SECRET is not set."
fi

mkdir -p var/cache var/log var/sessions public/uploads/images
chown -R www-data:www-data var public/uploads 2>/dev/null || true

# Warm cache BEFORE nginx — background warmup caused HTTP 500 on all PHP routes
echo "Warming Symfony cache..."
php bin/console cache:clear --env="${APP_ENV:-prod}" --no-warmup
php bin/console cache:warmup --env="${APP_ENV:-prod}"
php bin/console assets:install public --env="${APP_ENV:-prod}" --no-interaction 2>/dev/null || true

if [ -n "${DATABASE_URL:-}" ]; then
    echo "Checking database connection..."
    if php bin/console doctrine:query:sql "SELECT 1" --env="${APP_ENV:-prod}" 2>&1; then
        echo "Running database migrations..."
        php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env="${APP_ENV:-prod}" || \
            echo "WARNING: Migrations failed."
        su -s /bin/sh www-data -c "cd /app && php bin/console doctrine:query:sql 'SELECT 1' --env=${APP_ENV:-prod} --quiet" 2>/dev/null || {
            chmod 644 .env 2>/dev/null || true
            chown www-data:www-data .env 2>/dev/null || true
        }
    else
        echo "WARNING: Database connection failed. Login will not work until DATABASE_URL=\${{MySQL.MYSQL_URL}} is set."
    fi
fi

if [ -n "${DATABASE_URL:-}" ] && [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
    echo "Ensuring admin user exists..."
    php bin/console app:create-admin \
        --env="${APP_ENV:-prod}" \
        --no-interaction \
        --email="${ADMIN_EMAIL}" \
        --username="${ADMIN_USERNAME:-admin}" \
        --name="${ADMIN_NAME:-Administrator}" \
        --password="${ADMIN_PASSWORD}" \
        2>/dev/null || true
fi

php-fpm -D
echo "Ready: static GET /health + Symfony on port ${PORT}"
exec nginx -g 'daemon off;'
