#!/usr/bin/env sh
set -eu

write_env_file() {
    env_keys="
APP_NAME APP_ENV APP_KEY APP_DEBUG APP_URL
APP_LOCALE APP_FALLBACK_LOCALE APP_FAKER_LOCALE
LOG_CHANNEL LOG_LEVEL
DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
SESSION_DRIVER CACHE_STORE QUEUE_CONNECTION
FILESYSTEM_DISK
REDIS_CLIENT REDIS_HOST REDIS_PASSWORD REDIS_PORT
MAIL_MAILER
VITE_APP_NAME
"

    : > .env

    for key in $env_keys; do
        if printenv "$key" >/dev/null 2>&1; then
            value="$(printenv "$key")"
            printf '%s=%s\n' "$key" "$value" >> .env
        fi
    done

    chown www-data:www-data .env 2>/dev/null || true
}

wait_for_database() {
    if [ "${DB_CONNECTION:-}" != "pgsql" ] || [ -z "${DB_HOST:-}" ]; then
        return 0
    fi

    attempts=30

    until php -r '$host = getenv("DB_HOST"); $port = (int) (getenv("DB_PORT") ?: 5432); $socket = @fsockopen($host, $port, $errno, $errstr, 1); if ($socket) { fclose($socket); exit(0); } exit(1);'; do
        attempts=$((attempts - 1))

        if [ "$attempts" -le 0 ]; then
            echo "PostgreSQL is not reachable at ${DB_HOST}:${DB_PORT:-5432}" >&2
            return 1
        fi

        sleep 2
    done
}

artisan() {
    su -s /bin/sh www-data -c "php artisan $*"
}

mkdir -p \
    storage/app/public \
    storage/framework/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
rm -f public/hot

if [ ! -f .env ]; then
    write_env_file
fi

wait_for_database

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    artisan migrate --force
fi

artisan storage:link >/dev/null 2>&1 || true

if [ "${APP_ENV:-production}" = "production" ]; then
    artisan config:cache
    artisan route:cache
    artisan view:cache
else
    artisan optimize:clear
fi

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

exec "$@"
