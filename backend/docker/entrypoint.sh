#!/bin/sh
set -e

PORT="${PORT:-10000}"
export PORT

envsubst '${PORT}' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-available/default

cd /app
php artisan config:clear
php artisan migrate --force --seed

exec "$@"
