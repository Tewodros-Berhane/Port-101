# syntax=docker/dockerfile:1.7

FROM php:8.4-fpm-bookworm AS php-base

ARG APP_DIR=/var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

WORKDIR ${APP_DIR}

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

FROM php-base AS vendor

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --no-scripts

FROM node:22-bookworm-slim AS node-runtime

FROM php-base AS frontend-builder

ARG APP_DIR=/var/www/html

WORKDIR ${APP_DIR}

COPY --from=node-runtime /usr/local/bin /usr/local/bin
COPY --from=node-runtime /usr/local/include /usr/local/include
COPY --from=node-runtime /usr/local/lib /usr/local/lib
COPY --from=node-runtime /usr/local/share /usr/local/share

ENV APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=

COPY package.json package-lock.json ./
RUN npm ci

COPY composer.json composer.lock ./
COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .

RUN mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs

RUN npm run build

FROM php-base AS app

ARG APP_DIR=/var/www/html

ENV APP_DIR=${APP_DIR}

WORKDIR ${APP_DIR}

COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=frontend-builder /var/www/html/public/build ./public/build

RUN rm -rf storage bootstrap/cache/* \
    && mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage \
    && chown -R www-data:www-data ${APP_DIR}

COPY docker/php/conf.d/99-app.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php-fpm.d/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint

RUN chmod +x /usr/local/bin/app-entrypoint

USER www-data

ENTRYPOINT ["/usr/local/bin/app-entrypoint"]
CMD ["php-fpm", "-F"]

FROM nginx:1.27-alpine AS nginx

ARG APP_DIR=/var/www/html

WORKDIR ${APP_DIR}

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/errors/50x.html /usr/share/nginx/html/50x.html
COPY --from=app /var/www/html/public ./public

RUN ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage \
    && mkdir -p /var/www/html/storage/app/public
