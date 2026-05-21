#!/bin/sh
set -e

PORT="${PORT:-8080}"
export PORT

echo "Starting Pet Pantry on port ${PORT}..."

# Apply listen port to nginx (placeholder __PORT__ in default.conf)
sed -i "s/__PORT__/${PORT}/g" /etc/nginx/conf.d/default.conf

cd /app

# Symfony requires a readable .env in the container (.env is not copied into the image).
# Railway variables are written here so PHP-FPM and console commands see them.
write_env_file() {
    {
        printf 'APP_ENV=%s\n' "${APP_ENV:-prod}"
        printf 'APP_DEBUG=%s\n' "${APP_DEBUG:-0}"
        [ -n "${APP_SECRET:-}" ] && printf 'APP_SECRET=%s\n' "${APP_SECRET}"
        [ -n "${TRUSTED_PROXIES:-}" ] && printf 'TRUSTED_PROXIES=%s\n' "${TRUSTED_PROXIES}"
        [ -n "${DEFAULT_URI:-}" ] && printf 'DEFAULT_URI=%s\n' "${DEFAULT_URI}"
        [ -n "${DATABASE_URL:-}" ] && printf 'DATABASE_URL=%s\n' "${DATABASE_URL}"
        [ -n "${MESSENGER_TRANSPORT_DSN:-}" ] && printf 'MESSENGER_TRANSPORT_DSN=%s\n' "${MESSENGER_TRANSPORT_DSN}"
        [ -n "${MAILER_DSN:-}" ] && printf 'MAILER_DSN=%s\n' "${MAILER_DSN}"
        [ -n "${CORS_ALLOW_ORIGIN:-}" ] && printf 'CORS_ALLOW_ORIGIN=%s\n' "${CORS_ALLOW_ORIGIN}"
        [ -n "${GOOGLE_CLIENT_ID:-}" ] && printf 'GOOGLE_CLIENT_ID=%s\n' "${GOOGLE_CLIENT_ID}"
        [ -n "${GOOGLE_CLIENT_SECRET:-}" ] && printf 'GOOGLE_CLIENT_SECRET=%s\n' "${GOOGLE_CLIENT_SECRET}"
    } > .env
}
write_env_file

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
