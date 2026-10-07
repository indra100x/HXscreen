#!/bin/sh
set -e
cd /var/www/html/laravel

# First boot: ensure an APP_KEY exists (compose should provide one).
if [ -z "$APP_KEY" ] && ! grep -qE '^APP_KEY=.+' .env 2>/dev/null; then
    [ -f .env ] || cp .env.example .env
    php artisan key:generate --force
fi

touch database/database.sqlite
php artisan migrate --force
# Install-time owner login (single-business package). Idempotent: creates or
# updates OWNER_EMAIL with OWNER_PASSWORD and ensures its venue exists.
# Skipped silently when OWNER_EMAIL/OWNER_PASSWORD are not set (local dev).
if [ -n "$OWNER_EMAIL" ] && [ -n "$OWNER_PASSWORD" ]; then
    php artisan app:ensure-owner --force || echo "WARNING: app:ensure-owner failed (login may not work)"
fi
php artisan storage:link || true
php artisan optimize
chown -R www-data:www-data storage bootstrap/cache database

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/hxscreen.conf
