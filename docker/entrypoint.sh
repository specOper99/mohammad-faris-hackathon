#!/bin/sh
set -e
cd /var/www/html
until php -r "try { new PDO('pgsql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}', getenv('DB_USERNAME'), getenv('DB_PASSWORD')); echo 'ok'; } catch (Throwable \$e) { exit(1); }"; do
  echo "waiting for postgres..."
  sleep 2
done
php artisan migrate --force
if [ "$1" = "php-fpm" ]; then
  exec php-fpm
fi
exec "$@"
