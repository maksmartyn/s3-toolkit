FROM ghcr.io/roadrunner-server/roadrunner:2025.1.15 AS roadrunner
FROM composer:2 AS composer
FROM node:22-bookworm-slim AS node

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
