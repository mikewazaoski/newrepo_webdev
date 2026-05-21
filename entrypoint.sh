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
# Symfony reads /app/.env.local.php; PHP-FPM keeps container env via clear_env=no

mkdir -p var/cache var/log public/uploads/images public/bundles /tmp/petpantry-sessions
chmod 644 .env .env.local.php 2>/dev/null || true
chown -R www-data:www-data var public .env .env.local.php /tmp/petpantry-sessions 2>/dev/null || true
chmod -R 775 var public/bundles /tmp/petpantry-sessions 2>/dev/null || true

if [ -z "${APP_SECRET:-}" ]; then
    echo "WARNING: APP_SECRET is not set. Add a random secret in Railway → app service → Variables."
fi

if [ -n "${DATABASE_URL:-}" ]; then
    echo "Database target host: $(php -r 'echo parse_url(getenv("DATABASE_URL"), PHP_URL_HOST) ?: "unknown";')"
else
    echo "WARNING: DATABASE_URL is empty — add MySQL and set DATABASE_URL=\${{MySQL.MYSQL_URL}} on the app service."
fi

if [ -n "${MYSQLHOST:-}" ]; then
    echo "MySQL linked via MYSQLHOST=${MYSQLHOST}"
else
    echo "WARNING: MYSQLHOST is not set — link MySQL to this app service in Railway."
fi

# Symfony cache + DB must finish before PHP-FPM serves requests (otherwise registration hits a dead DB)
echo "Warming Symfony cache as www-data..."
su -s /bin/sh www-data -c "cd /app && php bin/console cache:clear --env=${APP_ENV:-prod} --no-warmup"
su -s /bin/sh www-data -c "cd /app && php bin/console cache:warmup --env=${APP_ENV:-prod}"
su -s /bin/sh www-data -c "cd /app && php bin/console assets:install public --env=${APP_ENV:-prod} --no-interaction" 2>/dev/null || true

if [ -n "${DATABASE_URL:-}" ]; then
    echo "Checking database connection..."
    if su -s /bin/sh www-data -c "cd /app && php bin/console dbal:run-sql 'SELECT 1' --env=${APP_ENV:-prod} --quiet" 2>/dev/null; then
        echo "Running database migrations..."
        su -s /bin/sh www-data -c "cd /app && php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=${APP_ENV:-prod}" || \
            echo "WARNING: Migrations failed."
        echo "Database OK for PHP-FPM (www-data can read .env and connect)."
    else
        echo "WARNING: Cannot connect to database."
        echo "  Railway: add MySQL service, then set DATABASE_URL=\${{MySQL.MYSQL_URL}} on the app service."
        echo "  Remove any DATABASE_URL pointing to 127.0.0.1, localhost, or @mysql: (Docker-only)."
        su -s /bin/sh www-data -c "cd /app && php bin/console dbal:run-sql 'SELECT 1' --env=${APP_ENV:-prod}" 2>&1 || true
    fi
else
    echo "WARNING: No DATABASE_URL — skipping migrations."
fi

php-fpm -D
echo "Nginx listening on port ${PORT} (GET /health for Railway healthcheck)..."
exec nginx -g 'daemon off;'
