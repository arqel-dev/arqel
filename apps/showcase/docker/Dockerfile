FROM php:8.4-fpm-alpine AS base
RUN apk add --no-cache nginx supervisor postgresql-dev oniguruma-dev libzip-dev icu-dev \
    && docker-php-ext-install pdo pdo_pgsql bcmath zip intl opcache pcntl \
    && apk add --no-cache --virtual .build-deps autoconf g++ make \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-arqel.ini
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
