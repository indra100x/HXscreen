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
php artisan storage:link || true
php artisan optimize
chown -R www-data:www-data storage bootstrap/cache database

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/hxscreen.conf
