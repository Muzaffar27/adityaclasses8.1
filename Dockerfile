FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libsqlite3-dev libzip-dev \
    && docker-php-ext-install pdo_mysql pdo_sqlite zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html/adityaclasses
