FROM php:8.2-fpm-alpine

LABEL org.opencontainers.image.authors="tchap@tchap.me"
LABEL org.opencontainers.image.url="https://github.com/tchapi/davis/pkgs/container/davis"
LABEL org.opencontainers.image.description="A simple, fully translatable admin interface for sabre/dav based on Symfony 5 and Bootstrap 4"

# Run update, and gets basic packages and packages for runtime
RUN apk --no-progress --update add --no-cache \
        curl unzip \
        # These are for php-intl
        icu-libs \
        # This one is for IMAP (to provide libc-client.so)
        c-client \
        # This one for LDAP
        libldap \
        # These are for GD (map image in mail)
        freetype \
        libjpeg-turbo \
        libpng \
        # This is for PostgreSQL
        libpq

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

# IMAP auth support
RUN apk --update --virtual build-deps-imap add --no-cache imap-dev openssl-dev krb5-dev \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install imap \
    && apk del build-deps-imap \
    && rm -rf /tmp/*

# Set timezone correctly
RUN echo 'date.timezone = "Europe/Paris"' > /usr/local/etc/php/conf.d/timezone.ini

# Davis installation
ADD . /var/www/davis
WORKDIR /var/www/davis

# Install Composer 2, then dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN APP_ENV=prod composer install --no-ansi --no-dev --no-interaction --no-progress --optimize-autoloader

# Cleanup (only useful when using --squash)
RUN docker-php-source delete
RUN rm -rf /usr/local/bin/composer

RUN chown -R www-data:www-data var
