# syntax=docker/dockerfile:1

# Initial PHP image is available here:
# https://github.com/docker-library/php/blob/master/8.2/alpine3.18/fpm/Dockerfile#L33
ARG fpm_user=82:82

# Base image, used to build extensions and the final image ———————————————————————
FROM php:8.3-fpm-alpine AS base-image

# Run update, and gets basic packages and packages for runtime
RUN apk --no-progress --update add --no-cache \
        curl unzip fcgi \
        # These are for php-intl
        icu-libs \
        # This one for LDAP
        libldap \
        # This one for IMAP
        libzip \
        # These are for GD (map image in mail)
        freetype \
        libjpeg-turbo \
        libpng \
        # This is for PostgreSQL
        libpq

# Build all extensions in a separate image ———————————————————————————————————————
FROM base-image AS extension-builder

# Install ALL build dependencies at once
RUN apk --update --virtual build-deps add --no-cache \
    # Intl support
    icu-dev \
    # PDO: PostgreSQL
    libpq-dev \
    # GD (map image in mail)
    freetype-dev libjpeg-turbo-dev libpng-dev \
    # LDAP auth support
    openldap-dev \
    # Zip lib for PHP-IMAP
    libzip-dev \
    $PHPIZE_DEPS

# Build ALL extensions in one RUN command to reduce layers
# and allow parallel compilation for extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-configure gd --with-freetype \
    && docker-php-ext-configure ldap \
    && docker-php-ext-configure zip \
    && docker-php-ext-install -j$(nproc) \
        intl \
        pdo_mysql \
        pgsql \
        pdo_pgsql \
        gd \
        ldap \
        zip \
        opcache \
    && docker-php-ext-enable gd opcache \
    && apk del build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

COPY ./docker/configurations/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Final image ————————————————————————————————————————————————————————————————————
FROM base-image

ARG fpm_user=82:82
ENV FPM_USER=${fpm_user}

ENV PHP_OPCACHE_MEMORY_CONSUMPTION="256" \
    PHP_OPCACHE_MAX_WASTED_PERCENTAGE="10"

LABEL org.opencontainers.image.authors="tchap@tchap.me"
LABEL org.opencontainers.image.url="https://github.com/tchapi/davis/pkgs/container/davis"
LABEL org.opencontainers.image.description="A simple, fully translatable admin interface for sabre/dav based on Symfony 7 and Bootstrap 5"

# Rapatriate built extensions
COPY --from=extension-builder /usr/local/etc/php/conf.d     /usr/local/etc/php/conf.d/
COPY --from=extension-builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions/

# PHP-FPM healthcheck
RUN set -xe && echo "pm.status_path = /status" >> /usr/local/etc/php-fpm.d/zz-docker.conf
RUN curl https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/v0.5.0/php-fpm-healthcheck \
    -o /usr/local/bin/php-fpm-healthcheck -s \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

# Davis installation
ADD --chown=${FPM_USER} . /var/www/davis
WORKDIR /var/www/davis

# Install Composer 2, then dependencies, compress the rather big INTL package
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN APP_ENV=prod COMPOSER_ALLOW_SUPERUSER=1 composer install --no-ansi --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader \
    && php ./vendor/symfony/intl/Resources/bin/compress

# Cleanup (only useful when using --squash)
RUN rm -rf /var/www/davis/docker

USER $FPM_USER

HEALTHCHECK --interval=30s --timeout=1s CMD php-fpm-healthcheck || exit 1
