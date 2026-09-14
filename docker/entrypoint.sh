#!/bin/sh
set -e
cd /app

# Key: ensure .env exists (from the template shipped in the image), generate key once
if [ ! -f .env ]; then
    cp .env.example .env
fi
if [ -z "$(grep -E '^APP_KEY=base64:.+' .env 2>/dev/null)" ]; then
    php artisan key:generate --force
fi

# Database: migrate + seed fresh on first boot; keep the file across restarts
if [ ! -f /app/database/database.sqlite ]; then
    touch /app/database/database.sqlite
    php artisan migrate --force --seed
else
    php artisan migrate --force
fi

exec "$@"
