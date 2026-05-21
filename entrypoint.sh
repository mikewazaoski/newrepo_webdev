#!/bin/sh
set -e

PORT="${PORT:-8080}"
export PORT

echo "Starting Pet Pantry on port ${PORT}..."

# Apply listen port to nginx (placeholder __PORT__ in default.conf)
sed -i "s/__PORT__/${PORT}/g" /etc/nginx/conf.d/default.conf

cd /app

# Production cache & assets
php bin/console cache:clear --env="${APP_ENV:-prod}" --no-warmup 2>/dev/null || true
php bin/console cache:warmup --env="${APP_ENV:-prod}" 2>/dev/null || true
php bin/console assets:install public --env="${APP_ENV:-prod}" --no-interaction 2>/dev/null || true

# Run migrations when database is configured (Railway MySQL)
if [ -n "${DATABASE_URL}" ]; then
    echo "Running database migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true
fi

mkdir -p var/cache var/log public/uploads/images
chown -R www-data:www-data var public/uploads 2>/dev/null || true

php-fpm -D
exec nginx -g 'daemon off;'
