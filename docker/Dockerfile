FROM php:7.4-fpm

LABEL maintainer="tchap@tchap.me"

# Run update, and gets basic packages
RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        unzip \
        # There are for php-intl
        zlib1g-dev libicu-dev g++ \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure PHP extensions
RUN docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-install intl \
    && docker-php-source delete

# Set timezone correctly
RUN echo 'date.timezone = "Europe/Paris"' > /usr/local/etc/php/conf.d/timezone.ini

# Davis installation
ADD . /var/www/davis
WORKDIR /var/www/davis

RUN rm -rf docker

# Install Composer 2, then dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --version=2.0.12
RUN APP_ENV=prod composer install --no-ansi --no-dev --no-interaction --no-progress --optimize-autoloader

RUN chown -R www-data:www-data var
