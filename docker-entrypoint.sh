#!/bin/bash
set -e

# Copy .env.example to .env if no .env exists (Railway injects vars via environment)
if [ ! -f /var/www/html/.env ]; then
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Run artisan commands now that the environment is fully available
php artisan storage:link --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec apache2-foreground
