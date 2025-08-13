FROM php:8.4-fpm-alpine

RUN apk add --no-cache git curl libpq-dev $PHPIZE_DEPS \
  && docker-php-ext-install pdo pdo_pgsql \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && apk del $PHPIZE_DEPS

WORKDIR /var/www