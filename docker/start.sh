#!/bin/sh

set -e

echo "=========================================="
echo "        FacultyHUB Production Start       "
echo "=========================================="

echo ""
echo "Checking Laravel environment..."

if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY is not set."
    exit 1
fi

echo ""
echo "Runtime database configuration:"
echo "DB_CONNECTION=${DB_CONNECTION}"
echo "DB_HOST=${DB_HOST}"
echo "DB_PORT=${DB_PORT}"
echo "DB_DATABASE=${DB_DATABASE}"
echo "DB_USERNAME=${DB_USERNAME}"

echo ""
echo "=========================================="
echo "Running database migrations..."
echo "=========================================="

php artisan migrate --force

echo ""
echo "Database migrations completed."

echo ""
echo "=========================================="
echo "Running database seeders..."
echo "=========================================="

php artisan db:seed --force

echo ""
echo "Database seeding completed."

echo ""
echo "=========================================="
echo "Clearing Laravel caches..."
echo "=========================================="

php artisan optimize:clear

echo ""
echo "=========================================="
echo "Caching Laravel configuration..."
echo "=========================================="

php artisan config:cache

echo ""
echo "=========================================="
echo "Caching Laravel routes..."
echo "=========================================="

php artisan route:cache || true

echo ""
echo "=========================================="
echo "Caching Laravel views..."
echo "=========================================="

php artisan view:cache || true

echo ""
echo "=========================================="
echo "Starting FacultyHUB..."
echo "=========================================="

export PORT="${PORT:-8080}"

echo "Application port: ${PORT}"

exec /usr/bin/supervisord \
    -c /etc/supervisord.conf