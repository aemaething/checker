#!/bin/sh
set -e

# Cache config/routes/views for faster startup in production
if [ "${APP_ENV}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Run pending migrations (idempotent)
php artisan migrate --force

exec "$@"
