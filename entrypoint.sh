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

mkdir -p var/cache var/log var/sessions public/uploads/images /tmp/petpantry-sessions
chmod 644 .env 2>/dev/null || true
chown -R www-data:www-data var public/uploads .env /tmp/petpantry-sessions 2>/dev/null || true
chmod -R 775 var /tmp/petpantry-sessions 2>/dev/null || true

if [ -n "${DATABASE_URL:-}" ]; then
    echo "Database target host: $(php -r 'echo parse_url(getenv("DATABASE_URL"), PHP_URL_HOST) ?: "unknown";')"
else
    echo "WARNING: DATABASE_URL is empty — set DATABASE_URL=\${{MySQL.MYSQL_URL}} on Railway."
fi

# Run Symfony console as www-data (same user as PHP-FPM) so cache and sessions are readable
echo "Warming Symfony cache as www-data..."
su -s /bin/sh www-data -c "cd /app && php bin/console cache:clear --env=${APP_ENV:-prod} --no-warmup"
su -s /bin/sh www-data -c "cd /app && php bin/console cache:warmup --env=${APP_ENV:-prod}"
su -s /bin/sh www-data -c "cd /app && php bin/console assets:install public --env=${APP_ENV:-prod} --no-interaction" 2>/dev/null || true

if [ -n "${DATABASE_URL:-}" ]; then
    echo "Checking database connection..."
    if su -s /bin/sh www-data -c "cd /app && php bin/console doctrine:query:sql 'SELECT 1' --env=${APP_ENV:-prod} --quiet" 2>/dev/null; then
        echo "Running database migrations..."
        su -s /bin/sh www-data -c "cd /app && php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=${APP_ENV:-prod}" || \
            echo "WARNING: Migrations failed."
    else
        echo "WARNING: Database connection failed. Set DATABASE_URL=\${{MySQL.MYSQL_URL}} on Railway (remove Docker URLs)."
        su -s /bin/sh www-data -c "cd /app && php bin/console doctrine:query:sql 'SELECT 1' --env=${APP_ENV:-prod}" 2>&1 || true
    fi
fi

if [ -n "${DATABASE_URL:-}" ] && [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
    echo "Ensuring admin user exists..."
    su -s /bin/sh www-data -c "cd /app && php bin/console app:create-admin --no-interaction --email=${ADMIN_EMAIL} --username=${ADMIN_USERNAME:-admin} --name='${ADMIN_NAME:-Administrator}' --password=${ADMIN_PASSWORD} --env=${APP_ENV:-prod}" 2>/dev/null || true
fi

php-fpm -D
echo "Ready on port ${PORT} — GET /health (static), Symfony pages use /tmp/petpantry-sessions"
exec nginx -g 'daemon off;'
