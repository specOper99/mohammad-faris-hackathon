FROM php:8.4-fpm-alpine

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1

RUN apk add --no-cache \
        icu-dev \
        postgresql-dev \
        libzip-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        linux-headers \
        unzip \
        git \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install intl pdo_pgsql zip opcache pcntl gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php.ini /usr/local/etc/php/conf.d/exoplanet.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-exoplanet.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && sed -i 's/\r$//' /usr/local/etc/php/conf.d/exoplanet.ini \
    && sed -i 's/\r$//' /usr/local/etc/php-fpm.d/zz-exoplanet.conf \
    && chmod +x /usr/local/bin/entrypoint.sh

COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
