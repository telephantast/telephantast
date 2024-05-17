#syntax=docker/dockerfile:1.4
FROM php:8.3-cli-alpine3.17

RUN set -eux && \
    apk update && \
    apk add --no-cache patch && \
    apk del && \
    rm -rf /var/lib/apt/lists/* && \
    mkdir /root/history && \
    echo 'alias sf="php bin/console"' >> ~/.bashrc

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions @composer opcache pdo_pgsql pgsql intl pcov pcntl sockets && \
    ln -s composer /usr/local/bin/c

COPY --link php.ini $PHP_INI_DIR/conf.d/
RUN set -eux && \
    cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" && \
    mkdir /var/run/php

WORKDIR /var/www/html

CMD composer install
