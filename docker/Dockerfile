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

# Intl support
RUN apk --update --virtual build-deps-intl add --no-cache icu-dev \
    && docker-php-ext-install intl \
    && apk del build-deps-intl \
    && rm -rf /tmp/*

# PDO: MySQL
RUN docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd \
    && docker-php-ext-install pdo_mysql

# PDO: PostgreSQL
RUN apk --update --virtual build-deps-pg add --no-cache libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pgsql pdo_pgsql \
    && apk del build-deps-pg \
    && rm -rf /tmp/*

# GD (map image in mail)
RUN apk --update --virtual build-deps-gd add --no-cache freetype-dev libjpeg-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-freetype \
    && docker-php-ext-install gd \
    && docker-php-ext-enable gd \
    && apk del build-deps-gd \
    && rm -rf /tmp/*

# LDAP auth support
RUN apk --update --virtual build-deps-ldap add --no-cache openldap-dev \
    && docker-php-ext-configure ldap \
    && docker-php-ext-install ldap \
    && apk del build-deps-ldap \
    && rm -rf /tmp/*

# Zip lib for PHP-IMAP
RUN apk --update --virtual build-deps-zip add --no-cache libzip-dev \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip \
    && apk del build-deps-zip \
    && rm -rf /tmp/*

# OPCache
RUN docker-php-ext-install opcache
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
RUN APP_ENV=prod COMPOSER_ALLOW_SUPERUSER=1 composer install --no-ansi --no-dev --no-interaction --no-progress --optimize-autoloader \
    && php ./vendor/symfony/intl/Resources/bin/compress

# Cleanup (only useful when using --squash)
RUN rm -rf /var/www/davis/docker

USER $FPM_USER

HEALTHCHECK --interval=30s --timeout=1s CMD php-fpm-healthcheck || exit 1
