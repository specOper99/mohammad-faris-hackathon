FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    icu-dev \
    postgresql-dev \
    libzip-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-install intl pdo_pgsql zip opcache pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php.ini /usr/local/etc/php/conf.d/exoplanet.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-exoplanet.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
