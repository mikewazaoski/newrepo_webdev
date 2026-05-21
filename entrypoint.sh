#!/bin/sh
set -e

PORT="${PORT:-80}"
export PORT

echo "Starting Pet Pantry on port ${PORT}..."

# Apply listen port to nginx (placeholder __PORT__ in default.conf)
sed -i "s/__PORT__/${PORT}/g" /etc/nginx/conf.d/default.conf

cd /app

# Resolve MYSQL_URL / MYSQLHOST → DATABASE_URL and write /app/.env for Symfony + PHP-FPM
php bin/railway-env.php
eval "$(php bin/railway-env.php --shell)"
# PHP-FPM reads container env via clear_env=no (docker/php-fpm/zz-railway.conf); Symfony reads /app/.env

mkdir -p var/cache var/log public/uploads/images /tmp/petpantry-sessions
chmod 644 .env 2>/dev/null || true
chown -R www-data:www-data var public/uploads .env /tmp/petpantry-sessions 2>/dev/null || true
chmod -R 775 var /tmp/petpantry-sessions 2>/dev/null || true

if [ -z "${APP_SECRET:-}" ]; then
    echo "WARNING: APP_SECRET is not set. Add a random secret in Railway → app service → Variables."
fi

if [ -n "${DATABASE_URL:-}" ]; then
    echo "Database target host: $(php -r 'echo parse_url(getenv("DATABASE_URL"), PHP_URL_HOST) ?: "unknown";')"
else
    echo "WARNING: DATABASE_URL is empty — add MySQL and set DATABASE_URL=\${{MySQL.MYSQL_URL}} on the app service."
fi

php-fpm -D

# Symfony setup in background — nginx serves static GET /health immediately for Railway
(
    echo "Running Symfony cache warmup and migrations as www-data..."
    su -s /bin/sh www-data -c "cd /app && php bin/console cache:clear --env=${APP_ENV:-prod} --no-warmup"
    su -s /bin/sh www-data -c "cd /app && php bin/console cache:warmup --env=${APP_ENV:-prod}"
    su -s /bin/sh www-data -c "cd /app && php bin/console assets:install public --env=${APP_ENV:-prod} --no-interaction" 2>/dev/null || true

    if [ -n "${DATABASE_URL:-}" ]; then
        if su -s /bin/sh www-data -c "cd /app && php bin/console doctrine:query:sql 'SELECT 1' --env=${APP_ENV:-prod} --quiet" 2>/dev/null; then
            echo "Running database migrations..."
            su -s /bin/sh www-data -c "cd /app && php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=${APP_ENV:-prod}" || \
                echo "WARNING: Migrations failed."
            echo "Database OK for PHP-FPM (www-data can read .env and connect)."
        else
            echo "WARNING: Cannot connect to database — Symfony pages may fail until DATABASE_URL is fixed."
            su -s /bin/sh www-data -c "cd /app && php bin/console doctrine:query:sql 'SELECT 1' --env=${APP_ENV:-prod}" 2>&1 || true
        fi
    else
        echo "WARNING: No DATABASE_URL — skipping migrations."
    fi

    echo "Symfony setup complete."
) &

echo "Nginx listening on port ${PORT} (GET /health for Railway healthcheck)..."
exec nginx -g 'daemon off;'
