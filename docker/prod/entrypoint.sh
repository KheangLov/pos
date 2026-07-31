#!/usr/bin/env bash
# Container entrypoint for the production image.
#
# Every service (app, queue, scheduler, reverb) runs the same image and the
# same entrypoint; only the command differs. Shared first-boot setup happens
# here, then the container's actual command takes over as PID 1.
set -euo pipefail

APP_ROOT=/var/www/html
cd "$APP_ROOT"

# The storage tree is a named volume, so it starts empty and hides whatever
# the image had at that path. Rebuild the structure Laravel expects on every
# boot rather than assuming a previous run created it.
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

wait_for() {
    local label="$1" host="$2" port="$3" waited=0

    until (echo >"/dev/tcp/${host}/${port}") >/dev/null 2>&1; do
        if [ "$waited" -ge 120 ]; then
            echo "entrypoint: gave up waiting for ${label} at ${host}:${port}" >&2
            return 1
        fi
        [ "$waited" -eq 0 ] && echo "entrypoint: waiting for ${label}..."
        sleep 2
        waited=$((waited + 2))
    done
}

wait_for "database" "${DB_HOST:-pgsql}" "${DB_PORT:-5432}"
wait_for "redis" "${REDIS_HOST:-redis}" "${REDIS_PORT:-6379}"

# Only the web service migrates — running it from four containers at once
# would race. compose.prod.yaml sets RUN_MIGRATIONS on laravel.test alone.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "entrypoint: running migrations"
    php artisan migrate --force --no-interaction
fi

# Config/route/view/event caches are rebuilt per boot: they must reflect the
# environment this container was actually started with, and the image may have
# been built long before this machine ever saw it.
php artisan optimize:clear >/dev/null 2>&1 || true
php artisan optimize --no-interaction
php artisan filament:optimize --no-interaction

if [ "${SHOW_LICENSE_STATUS:-false}" = "true" ]; then
    php artisan license:show || true
fi

exec "$@"
