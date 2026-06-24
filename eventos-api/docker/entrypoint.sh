#!/bin/sh
# ════════════════════════════════════════════════════════════════════
# Shared entrypoint for api / horizon / reverb / scheduler.
# Only the `api` service sets INSTALL_DEPS=true + RUN_MIGRATIONS=true,
# so composer install and migrations happen exactly once (no races).
# ════════════════════════════════════════════════════════════════════
set -e
cd /var/www/html

DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"

echo "[entrypoint] waiting for postgres at ${DB_HOST}:${DB_PORT}..."
until pg_isready -h "${DB_HOST}" -p "${DB_PORT}" >/dev/null 2>&1; do
  sleep 1
done
echo "[entrypoint] postgres is ready."

if [ "${INSTALL_DEPS:-false}" = "true" ]; then
  if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] installing composer dependencies (first run)..."
    composer install --no-interaction --prefer-dist --no-progress
  fi
else
  echo "[entrypoint] waiting for vendor/autoload.php (installed by api service)..."
  until [ -f vendor/autoload.php ]; do sleep 2; done
fi

# Dev: ensure writable runtime dirs
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
         storage/logs bootstrap/cache
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

if [ "${INSTALL_DEPS:-false}" = "true" ]; then
  if [ -f .env ] && ! grep -q "^APP_KEY=base64:" .env; then
    echo "[entrypoint] generating APP_KEY..."
    php artisan key:generate --force || true
  fi
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "[entrypoint] running migrations as the migrator role..."
  php artisan migrate --force --database=pgsql_admin \
    || echo "[entrypoint] migrate skipped/failed — run 'make migrate' once configured."
fi

echo "[entrypoint] exec: $*"
exec "$@"
