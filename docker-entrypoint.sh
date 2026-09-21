#!/bin/bash
set -e

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force --seed || echo "Migration/seeding warning"

# Cache configuration, routes, and views for production optimization
echo "Caching Laravel configuration..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

exec "$@"
