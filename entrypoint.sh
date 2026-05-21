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

if [ -z "${APP_SECRET:-}" ]; then
    echo "WARNING: APP_SECRET is not set. Add a random secret in Railway → app service → Variables."
fi

# Production cache & assets
php bin/console cache:clear --env="${APP_ENV:-prod}" --no-warmup 2>/dev/null || true
php bin/console cache:warmup --env="${APP_ENV:-prod}" 2>/dev/null || true
php bin/console assets:install public --env="${APP_ENV:-prod}" --no-interaction 2>/dev/null || true

# Run migrations when database is configured (Railway MySQL)
if [ -n "${DATABASE_URL:-}" ]; then
    echo "Checking database connection..."
    if php bin/console doctrine:query:sql "SELECT 1" --env="${APP_ENV:-prod}" --quiet 2>/dev/null; then
        echo "Running database migrations..."
        php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env="${APP_ENV:-prod}"
    else
        echo "ERROR: Cannot connect to database. Set DATABASE_URL, for example:"
        echo "  Docker Compose: mysql://pets_user:pets_password@mysql:3306/pets_db?serverVersion=8.0&charset=utf8mb4"
        echo "  Railway:        DATABASE_URL=\${{MySQL.MYSQL_URL}}"
        exit 1
    fi
else
    echo "WARNING: No DATABASE_URL / MYSQL_URL — skipping migrations."
fi

mkdir -p var/cache var/log public/uploads/images
chown -R www-data:www-data var public/uploads 2>/dev/null || true

php-fpm -D
exec nginx -g 'daemon off;'
