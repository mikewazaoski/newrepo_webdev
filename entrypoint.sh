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
php bin/write-fpm-env.php
chmod 644 .env 2>/dev/null || true
chown www-data:www-data .env 2>/dev/null || true

if [ -n "${DATABASE_URL:-}" ]; then
    DB_HOST=$(php -r 'echo parse_url(getenv("DATABASE_URL"), PHP_URL_HOST) ?: "unknown";')
    echo "Database target host: ${DB_HOST}"
else
    echo "WARNING: DATABASE_URL is empty after railway-env.php"
fi

if [ -z "${APP_SECRET:-}" ]; then
    echo "WARNING: APP_SECRET is not set. Add a random secret in Railway → app service → Variables."
fi

# Production cache & assets (must succeed or Symfony returns 500 on every page)
php bin/console cache:clear --env="${APP_ENV:-prod}" --no-warmup
if ! php bin/console cache:warmup --env="${APP_ENV:-prod}"; then
    echo "ERROR: Symfony cache warmup failed. Check deploy logs for missing env vars (DEFAULT_URI, APP_SECRET, DATABASE_URL)."
    exit 1
fi
php bin/console assets:install public --env="${APP_ENV:-prod}" --no-interaction 2>/dev/null || true

# Run migrations when database is configured (Railway MySQL or Docker mysql service)
if [ -n "${DATABASE_URL:-}" ]; then
    echo "Checking database connection..."
    if php bin/console doctrine:query:sql "SELECT 1" --env="${APP_ENV:-prod}" --quiet 2>/dev/null; then
        echo "Running database migrations..."
        php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env="${APP_ENV:-prod}" || \
            echo "WARNING: Migrations failed — app will still start."
        echo "Verifying database as web user (www-data)..."
        if su -s /bin/sh www-data -c "cd /app && php bin/console doctrine:query:sql 'SELECT 1' --env=${APP_ENV:-prod} --quiet" 2>/dev/null; then
            echo "Database OK for PHP-FPM (www-data can read .env and connect)."
        else
            echo "WARNING: www-data cannot connect — fixing .env permissions..."
            chmod 644 .env 2>/dev/null || true
            chown www-data:www-data .env 2>/dev/null || true
            su -s /bin/sh www-data -c "cd /app && php bin/console doctrine:query:sql 'SELECT 1' --env=${APP_ENV:-prod} --quiet" 2>/dev/null || \
                echo "ERROR: PHP-FPM still cannot reach MySQL. Check DATABASE_URL=\${{MySQL.MYSQL_URL}} on Railway."
        fi
    else
        echo "ERROR: Cannot connect to database."
        echo "  Railway: add MySQL, set DATABASE_URL=\${{MySQL.MYSQL_URL}} on the app service (remove 127.0.0.1 / @mysql: URLs)."
        echo "  Docker:  DATABASE_URL=mysql://pets_user:pets_password@mysql:3306/pets_db?serverVersion=8.0&charset=utf8mb4"
        if [ -n "${RAILWAY_ENVIRONMENT:-}${RAILWAY_PROJECT_ID:-}" ]; then
            exit 1
        fi
    fi
else
    echo "WARNING: No DATABASE_URL / MYSQL_URL — skipping migrations."
    if [ -n "${RAILWAY_ENVIRONMENT:-}${RAILWAY_PROJECT_ID:-}" ]; then
        echo "ERROR: Railway deploy requires a valid DATABASE_URL (use \${{MySQL.MYSQL_URL}}, not 127.0.0.1 or @mysql:)."
        exit 1
    fi
fi

mkdir -p var/cache var/log var/sessions public/uploads/images
chown -R www-data:www-data var public/uploads 2>/dev/null || true

# Optional: seed first admin on Railway (set ADMIN_EMAIL, ADMIN_PASSWORD, etc. in Variables)
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
exec nginx -g 'daemon off;'
