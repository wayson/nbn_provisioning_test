FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        zip \
        mbstring \
        opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
