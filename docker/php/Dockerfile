FROM ghcr.io/roadrunner-server/roadrunner:2025.1.15 AS roadrunner
FROM composer:2 AS composer
FROM node:22-bookworm-slim AS node
# Клиент MinIO: управление ключами доступа MinIO (Admin API шифрует ответы так, что из PHP их не прочитать)
FROM bitnamilegacy/minio:latest AS minio

FROM php:8.4-cli-bookworm AS base

ARG UID=1000
ARG GID=1000

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip curl \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        redis \
        sockets \
        zip

COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=minio /opt/bitnami/minio-client/bin/mc /usr/local/bin/mc

RUN groupadd -g ${GID} app && useradd -u ${UID} -g app -m -s /bin/bash app

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html
USER app

FROM base AS dev

USER root

RUN install-php-extensions pcov

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

USER app

# Сборка: зависимости и фронтенд (Wayfinder при сборке вызывает artisan, поэтому нужен и PHP, и Node)
FROM dev AS build

USER root
COPY --chown=app:app . /var/www/html
USER app

RUN composer install --no-interaction --no-progress --prefer-dist \
    && npm ci --no-audit --no-fund \
    && npm run build \
    && composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader \
    && rm -rf node_modules

# Рабочий образ: код и собранный фронтенд, без Node и dev-зависимостей
FROM base AS prod

COPY --from=build --chown=app:app /var/www/html /var/www/html

ENV APP_ENV=production \
    APP_DEBUG=false \
    MC_BINARY=/usr/local/bin/mc
