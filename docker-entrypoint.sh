#!/bin/sh
set -eu

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
    echo 'APP_KEY is required. Configure a persistent Laravel application key in Coolify.' >&2
    exit 1
fi

mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan config:cache --no-interaction
php artisan view:cache --no-interaction
php artisan storage:link --no-interaction

exec docker-php-entrypoint "$@"
