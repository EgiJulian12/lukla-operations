#!/bin/sh
set -e

echo "Starting Lukla Operations Container..."

# Wait for PostgreSQL database if DB_HOST is configured
if [ -n "$DB_HOST" ]; then
    DB_PORT="${DB_PORT:-5432}"
    echo "Waiting for database at $DB_HOST:$DB_PORT..."
    while ! nc -z "$DB_HOST" "$DB_PORT" >/dev/null 2>&1; do
        sleep 1
    done
    echo "Database is reachable!"
fi

# Generate application key if not set
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is missing. Generating application key..."
    php artisan key:generate --force --no-interaction
fi

# Ensure storage symlink exists
if [ ! -L "/var/www/html/public/storage" ]; then
    echo "Creating storage symlink..."
    php artisan storage:link --no-interaction || true
fi

# Run database migrations if requested
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force --no-interaction
fi

# Run database seeder if requested
if [ "$RUN_SEEDER" = "true" ]; then
    echo "Seeding default operational accounts and data..."
    php artisan db:seed --force --no-interaction
fi

# Optimize Laravel cache for production
if [ "$APP_ENV" = "production" ]; then
    echo "Optimizing application cache for production..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# Ensure correct permissions for web server
echo "Setting directory permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "Lukla Operations ready. Executing command: $@"
exec "$@"
