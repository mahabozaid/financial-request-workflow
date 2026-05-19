# =============================================================================
# Stage 1 — Composer dependencies (isolated, cached layer)
# =============================================================================
FROM composer:2.8 AS composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --optimize --no-dev

# =============================================================================
# Stage 2 — Base PHP-FPM image (shared between production and testing)
# =============================================================================
FROM php:8.4-fpm-alpine AS base

# System dependencies
RUN apk add --no-cache \
    autoconf \
    bash \
    fcgi \
    g++ \
    libpq-dev \
    libzip-dev \
    make \
    oniguruma-dev \
    supervisor \
    unzip

# PHP extensions
RUN docker-php-ext-install \
    bcmath \
    mbstring \
    opcache \
    pdo \
    pdo_pgsql \
    zip \
    pcntl

# Redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

COPY docker/php/php.ini         /usr/local/etc/php/conf.d/app.ini
COPY docker/php/supervisord.conf /etc/supervisord.conf

# FPM ping endpoint + healthcheck script (uses cgi-fcgi from the fcgi package)
RUN echo '[www]' > /usr/local/etc/php-fpm.d/zz-health.conf \
    && echo 'ping.path = /ping' >> /usr/local/etc/php-fpm.d/zz-health.conf \
    && printf '#!/bin/sh\nSCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000\n' \
       > /usr/local/bin/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

WORKDIR /var/www/html

# =============================================================================
# Stage 3 — Production image
# =============================================================================
FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false

# Copy application files from composer stage
COPY --from=composer /app /var/www/html

# Permissions — www-data must own storage and bootstrap/cache
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 755 storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

# =============================================================================
# Stage 4 — Development / CI image (includes dev dependencies and xdebug)
# =============================================================================
FROM base AS development

RUN pecl install xdebug && docker-php-ext-enable xdebug

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

COPY . /var/www/html

RUN composer install --prefer-dist

RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
