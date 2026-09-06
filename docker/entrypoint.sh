#!/bin/sh
set -e
cd /var/www/html

DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-exoplanet}"
DB_USERNAME="${DB_USERNAME:-exoplanet}"
DB_PASSWORD="${DB_PASSWORD:-exoplanet}"
ROLE="${CONTAINER_ROLE:-}"

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ ! -f .env ]; then
  if [ -f .env.example ]; then
    cp .env.example .env
    echo "created .env from .env.example"
  else
    touch .env
  fi
fi

wait_for_postgres() {
  echo "waiting for postgres at ${DB_HOST}:${DB_PORT}/${DB_DATABASE}..."
  i=0
  until php -r "
try {
    new PDO(
        'pgsql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}',
        '${DB_USERNAME}',
        '${DB_PASSWORD}'
    );
} catch (Throwable \$e) {
    fwrite(STDERR, \$e->getMessage() . PHP_EOL);
    exit(1);
}
"; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
      echo "postgres not reachable after 120s"
      exit 1
    fi
    sleep 2
  done
  echo "postgres ready"
}

export_app_key_from_env_file() {
  APP_KEY=$(grep -E '^APP_KEY=' .env | tail -n 1 | cut -d= -f2- | tr -d '"' | tr -d "'" | tr -d '\r')
  export APP_KEY
}

ensure_app_key() {
  if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    export_app_key_from_env_file
  fi
  if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "APP_KEY empty — generating"
    php artisan key:generate --force
    export_app_key_from_env_file
  fi
  if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "FATAL: APP_KEY still empty after key:generate"
    exit 1
  fi
}

clear_bootstrap_cache() {
  # Named/bind-mounted caches from older images hide Scramble routes.
  rm -f bootstrap/cache/*.php
}

wait_for_vendor() {
  echo "waiting for vendor/autoload.php..."
  j=0
  until [ -f vendor/autoload.php ]; do
    j=$((j + 1))
    if [ "$j" -ge 90 ]; then
      echo "vendor/autoload.php missing after 180s"
      exit 1
    fi
    sleep 2
  done
}

if [ "$ROLE" = "fpm" ] || [ "$1" = "php-fpm" ]; then
  wait_for_postgres

  echo "composer install"
  composer_lock=/tmp/exoplanet-composer.lock
  n=0
  until mkdir "$composer_lock" 2>/dev/null; do
    n=$((n + 1))
    if [ "$n" -ge 90 ]; then
      echo "composer lock timeout"
      exit 1
    fi
    sleep 2
  done
  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts
  rmdir "$composer_lock" 2>/dev/null || true

  ensure_app_key
  clear_bootstrap_cache
  php artisan package:discover --ansi
  php artisan config:clear --ansi
  php artisan route:clear --ansi
  php artisan view:clear --ansi
  php artisan event:clear --ansi

  echo "running migrations"
  php artisan migrate --force

  if ! php artisan route:list --path=docs --no-ansi | grep -q 'docs/api'; then
    echo "FATAL: /docs/api is not registered. dedoc/scramble missing or not discovered."
    php artisan package:discover --ansi
    php artisan route:list --no-ansi || true
    exit 1
  fi
  echo "docs route registered"

  chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true
  chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
  touch /tmp/app-ready

  echo "starting php-fpm"
  exec php-fpm
fi

wait_for_vendor
wait_for_postgres
ensure_app_key

echo "waiting for migrations..."
k=0
until php artisan migrate:status; do
  k=$((k + 1))
  if [ "$k" -ge 60 ]; then
    echo "migrations not ready after 120s"
    exit 1
  fi
  echo "migrations not ready yet (${k})"
  sleep 2
done
echo "migrations ready"

exec "$@"
