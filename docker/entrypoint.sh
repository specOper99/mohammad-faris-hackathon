#!/bin/sh
set -e
cd /var/www/html

DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-exoplanet}"
DB_USERNAME="${DB_USERNAME:-exoplanet}"
DB_PASSWORD="${DB_PASSWORD:-exoplanet}"

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

echo "composer install"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
php artisan package:discover --ansi || true

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

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
  echo "APP_KEY empty — generating"
  php artisan key:generate --force
fi

if [ "$1" = "php-fpm" ]; then
  echo "running migrations"
  php artisan migrate --force
  echo "starting php-fpm"
  exec php-fpm
fi

echo "waiting for migrations..."
j=0
until php artisan migrate:status; do
  j=$((j + 1))
  if [ "$j" -ge 60 ]; then
    echo "migrations not ready after 120s"
    exit 1
  fi
  echo "migrations not ready yet (${j})"
  sleep 2
done
echo "migrations ready"

exec "$@"
