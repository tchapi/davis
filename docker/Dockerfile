FROM php:7.3-fpm

LABEL maintainer="tchap@tchap.me"

ARG version

# Mail : configuration for ssmtp
ARG email
ARG mail_host
ARG hostname
ARG mail_username
ARG mail_password

# Mail : compile ssmtp
RUN curl -O http://cdn-fastly.deb.debian.org/debian/pool/main/s/ssmtp/ssmtp_2.64.orig.tar.bz2 && \
    bunzip2 ssmtp_2.64.orig.tar.bz2 && \
    tar -xvf ssmtp_2.64.orig.tar && \
    cd ssmtp-2.64 && ./configure && make && \
    echo "echo 'Skipping config generation'" > generate_config && make install && \
    cd .. && rm -rf ssmtp-2.64 && rm ssmtp_2.64.orig.tar

COPY configurations/php-mail.ini /usr/local/etc/php/conf.d/mail.ini
RUN echo "root=${email}\nmailhub=${mail_host}\nrewriteDomain= \
          \nhostname=${hostname}\nFromLineOverride=YES \
          \nAuthUser=${mail_username}\nAuthPass=${mail_password}\n" > /usr/local/etc/ssmtp/ssmtp.conf

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

# Davis installation
RUN cd /var/www/ && curl --silent -o ${version}.tar.gz -L https://github.com/tchapi/davis/archive/v${version}.tar.gz \
 && tar xvzf ${version}.tar.gz \
 && mv /var/www/davis-${version} /var/www/davis \
 && rm ${version}.tar.gz

WORKDIR /var/www/davis

# Install dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN APP_ENV=prod composer install --no-ansi --no-dev --no-interaction --no-progress --optimize-autoloader
