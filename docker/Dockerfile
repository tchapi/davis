FROM php:8.1-fpm

LABEL org.opencontainers.image.authors="tchap@tchap.me"

# Run update, and gets basic packages
RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        unzip \
        # These are for IMAP
        libc-client-dev libkrb5-dev \
        # This one if for LDAP
        libldap2-dev \
        # This one if for GD (map image in mail)
        libpng-dev libjpeg-dev libfreetype6-dev \
        # There are for php-intl
        zlib1g-dev libicu-dev g++ \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure PHP extensions
RUN docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd \
    && docker-php-ext-install pdo_mysql

RUN docker-php-ext-configure ldap \
    && docker-php-ext-install ldap

RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install imap
    
RUN docker-php-ext-configure gd --with-freetype \
    && docker-php-ext-install gd
    
RUN docker-php-ext-install intl \
    && docker-php-source delete

# Set timezone correctly
RUN echo 'date.timezone = "Europe/Paris"' > /usr/local/etc/php/conf.d/timezone.ini

# Davis installation
ADD . /var/www/davis
WORKDIR /var/www/davis

# Install Composer 2, then dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN APP_ENV=prod composer install --no-ansi --no-dev --no-interaction --no-progress --optimize-autoloader

RUN chown -R www-data:www-data var
