#!/bin/bash
set -e

# Ensure APP_KEY is present
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY not set. Generating application key..."
    php artisan key:generate --force
fi

# Run database migrations and seeder
echo "Running database migrations..."
php artisan migrate --force --seed || echo "Migration/seeding warning"

# Cache configuration, routes, and views for production optimization
echo "Caching Laravel configuration..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

exec "$@"
