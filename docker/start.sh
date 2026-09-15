#!/bin/sh

set -e

echo "=========================================="
echo "        FacultyHUB Production Start       "
echo "=========================================="

echo ""
echo "Checking Laravel environment..."

# Make sure Laravel has an APP_KEY.
if [ -z "$APP_KEY" ]; then
    echo "WARNING: APP_KEY is not set."
    echo "Generating temporary APP_KEY..."

    php artisan key:generate --force
fi

echo ""
echo "Runtime database configuration:"
echo "DB_CONNECTION=${DB_CONNECTION}"
echo "DB_HOST=${DB_HOST}"
echo "DB_PORT=${DB_PORT}"
echo "DB_DATABASE=${DB_DATABASE}"
echo "DB_USERNAME=${DB_USERNAME}"

echo ""
echo "Clearing old Laravel caches..."

php artisan optimize:clear

echo ""
echo "Caching Laravel configuration..."

php artisan config:cache

echo ""
echo "Caching Laravel routes..."

php artisan route:cache || true

echo ""
echo "Caching Laravel views..."

php artisan view:cache || true

echo ""
echo "Running database migrations..."

php artisan migrate --force

echo ""
echo "Database migrations completed."

echo ""
echo "=========================================="
echo "Starting FacultyHUB..."
echo "=========================================="

# Render provides PORT automatically.
# Default to 8080 when running locally.
export PORT="${PORT:-8080}"

echo "Application port: ${PORT}"

# Start Supervisor.
exec /usr/bin/supervisord \
    -c /etc/supervisord.conf