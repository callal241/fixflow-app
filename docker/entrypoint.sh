#!/bin/sh
set -e
cd /app

# Key: ensure .env exists (from the template shipped in the image), generate key once
if [ ! -f .env ]; then
    cp .env.example .env
fi
# `php artisan serve` spawns the built-in web server WITHOUT inheriting Docker
# ENV vars, so the running app only sees what .env contains. Pin the DB path to
# the persistent volume (migrations come from the image at /app/database).
if grep -q '^DB_DATABASE=' .env 2>/dev/null; then
    sed -i 's|^DB_DATABASE=.*|DB_DATABASE=/data/database.sqlite|' .env
else
    printf '\nDB_DATABASE=/data/database.sqlite\n' >> .env
fi
if [ -z "$(grep -E '^APP_KEY=base64:.+' .env 2>/dev/null)" ]; then
    php artisan key:generate --force
fi

# Database: clear any cached config (DB path etc. may have changed between images),
# then migrate + seed fresh on first boot; keep the DB file across restarts
php artisan config:clear --force >/dev/null 2>&1 || true
mkdir -p /data
if [ ! -f /data/database.sqlite ]; then
    touch /data/database.sqlite
    php artisan migrate --force --seed
else
    php artisan migrate --force
fi

exec "$@"
