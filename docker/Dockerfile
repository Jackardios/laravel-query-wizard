ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli

RUN apt-get update -qq \
    && apt-get install -y -qq git unzip libzip-dev libsqlite3-dev \
    && docker-php-ext-install zip pdo pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
