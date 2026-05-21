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

# Run Symfony console as www-data (same user as PHP-FPM) so cache and sessions are readable
echo "Warming Symfony cache as www-data..."
su -s /bin/sh www-data -c "cd /app && php bin/console cache:clear --env=${APP_ENV:-prod} --no-warmup"
su -s /bin/sh www-data -c "cd /app && php bin/console cache:warmup --env=${APP_ENV:-prod}"
su -s /bin/sh www-data -c "cd /app && php bin/console assets:install public --env=${APP_ENV:-prod} --no-interaction" 2>/dev/null || true

# Run migrations when database is configured (Railway MySQL or Docker mysql service)
if [ -n "${DATABASE_URL:-}" ]; then
    echo "Checking database connection..."
    if su -s /bin/sh www-data -c "cd /app && php bin/console doctrine:query:sql 'SELECT 1' --env=${APP_ENV:-prod} --quiet" 2>/dev/null; then
        echo "Running database migrations..."
        su -s /bin/sh www-data -c "cd /app && php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=${APP_ENV:-prod}" || \
            echo "WARNING: Migrations failed — app will still start."
        echo "Database OK for PHP-FPM (www-data can read .env and connect)."
    else
        echo "WARNING: Cannot connect to database — app will start without migrations."
        echo "  Railway: add MySQL service, then set DATABASE_URL=\${{MySQL.MYSQL_URL}} on the app service."
        echo "  Docker:  DATABASE_URL=mysql://pets_user:pets_password@mysql:3306/pets_db?serverVersion=8.0&charset=utf8mb4"
        echo "  Remove any DATABASE_URL pointing to 127.0.0.1 or localhost on Railway."
        su -s /bin/sh www-data -c "cd /app && php bin/console doctrine:query:sql 'SELECT 1' --env=${APP_ENV:-prod}" 2>&1 || true
    fi
else
    echo "WARNING: No DATABASE_URL / MYSQL_URL — skipping migrations."
fi

php-fpm -D
echo "Ready on port ${PORT}"
exec nginx -g 'daemon off;'
