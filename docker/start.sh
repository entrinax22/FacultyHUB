#!/bin/sh
set -e

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Caching config, routes, views..."
php artisan optimize

echo "==> Starting services..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
