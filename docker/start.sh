#!/bin/sh
set -e

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Caching config, routes, views..."
php artisan optimize

echo "==> Waiting for PHP-FPM and Nginx to be ready..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
