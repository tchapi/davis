Davis
---

[![Build Status][ci_badge]][ci_link]
[![Publish Docker image](https://github.com/tchapi/davis/actions/workflows/main.yml/badge.svg?branch=master)](https://github.com/tchapi/davis/actions/workflows/main.yml)
[![Latest release][release_badge]][release_link]
[![Sponsor me][sponsor_badge]][sponsor_link]

A simple, fully translatable admin interface and frontend for `sabre/dav` based on [Symfony 5](https://symfony.com/) and [Bootstrap 4](https://getbootstrap.com/), initially inspired by [Baïkal](https://github.com/sabre-io/Baikal).

Provides user edition, calendar creation and sharing, address book creation and sharing. The interface is simple and straightforward, responsive, and provides a light and a dark mode.

Easily containerisable (_`Dockerfile` and sample `docker-compose` configuration file provided_).

Supports **Basic authentication**, as well as **IMAP** and **LDAP** (_via external providers_).

Created and maintained (with the help of the community) by [@tchapi](https://github.com/tchapi).

![Dashboard page](_screenshots/dashboard.png)
![User creation page](_screenshots/user.png)
![Sharing page](_screenshots/sharing.png)

# Requirements

  - PHP > 7.3.0 (with `pdo_mysql` [or `pdo_pgsql`], `gd` and `intl` extensions), compatible up to PHP 8.2
  - A compatible database layer, such as MySQL or MariaDB (recommended) or PostgreSQL (not extensively tested yet)
  - Composer > 2 (_The last release compatible with Composer 1 is [v1.6.2](https://github.com/tchapi/davis/releases/tag/v1.6.2)_)
  - The [`imap`](https://www.php.net/manual/en/imap.installation.php) and [`ldap`](https://www.php.net/manual/en/ldap.installation.php) PHP extensions if you want to use either authentication methods (_these are not enabled / compiled by default except in the Docker image_)

# Installation

0. Clone this repository

1. Retrieve the dependencies:

```
composer install
```

2. At least put the correct credentials to your database (driver and url) in your `.env.local` file so you can easily create the necessary tables.

3. Run the migrations to create all the necessary tables:

```
bin/console doctrine:migrations:migrate
```

**Davis** can also be used with a pre-existing MySQL database (_for instance, one previously managed by Baïkal_). See the paragraph "Migrating from Baikal" for more info.

> The tables are not _exactly_ equivalent to those of Baïkal, and allow for a bit more room in columns for instance (among other things)

## Configuration

Create your own `.env.local` file to change the necessary variables, if you plan on using `symfony/dotenv`.

> If your installation is behind a web server like Apache or Nginx, you can setup the env vars directly in your Apache or Nginx configuration (see below). Skip this part in this case.

a. The database driver and url (_you should already have it configured since you created the database previously_)
    
```
DATABASE_DRIVER=mysql # or postgresql
DATABASE_URL=mysql://db_user:db_pass@host:3306/db_name?serverVersion=mariadb-10.6.10&charset=utf8mb4
```

b. The admin password for the backend

```
ADMIN_LOGIN=admin
ADMIN_PASSWORD=test
```

c. The auth Realm and method for HTTP auth

```
AUTH_REALM=SabreDAV
AUTH_METHOD=Basic # can be "Basic", "IMAP" or "LDAP"
```
> See [the following paragraph](#specific-environment-variables-for-imap-and-ldap-authentication-methods) for more information if you choose either IMAP or LDAP.

d. The global flags to enable CalDAV, CardDAV and WebDAV

```
CALDAV_ENABLED=true
CARDDAV_ENABLED=true
WEBDAV_ENABLED=false
```

e. The email address that your invites are going to be sent from

```
INVITE_FROM_ADDRESS=no-reply@example.org
```

f. The paths for the WebDAV installation

> I recommend that you use absolute directories so you know exactly where your files reside.

```
WEBDAV_TMP_DIR='/tmp'
WEBDAV_PUBLIC_DIR='/webdav'
```

### Specific environment variables for IMAP and LDAP authentication methods

In case you use the `IMAP` auth type, you must specify the auth url (_the "mailbox" url_) in `IMAP_AUTH_URL`. See https://www.php.net/manual/en/function.imap-open.php for more details.

You should also explicitely define whether you want new authenticated to be created upon login:

```
IMAP_AUTH_URL="{imap.gmail.com:993/imap/ssl/novalidate-cert}"
IMAP_AUTH_USER_AUTOCREATE=true # false by default
```

Same goes for LDAP, where you must specify the LDAP server url, the DN pattern, the Mail attribute, as well as whether you want new authenticated to be created upon login (_like for IMAP_):

```
LDAP_AUTH_URL="ldap://127.0.0.1"
LDAP_DN_PATTERN="mail=%u"
LDAP_MAIL_ATTRIBUTE="mail"
LDAP_AUTH_USER_AUTOCREATE=true # false by default
```

> Ex: for [Zimbra LDAP](https://zimbra.github.io/adminguide/latest/#zimbra_ldap_service), you might want to use the `zimbraMailDeliveryAddress` attribute to retrieve the principal user email:
>    ```
>    LDAP_MAIL_ATTRIBUTE="zimbraMailDeliveryAddress"
>    ```

## Migrating from Baïkal?

If you're migrating from Baïkal, then you will likely want to do the following :

1. Get a backup of your data (without the `CREATE`  statements, but with complete `INSERT`  statements):

```
mysqldump -u root -p --no-create-info --complete-insert baikal > baikal_to_davis.sql # baikal is the actual name of your database
```


2. Create a new database for Davis (let's name it `davis`) and create the base schema:

```
bin/console doctrine:migrations:migrate 'DoctrineMigrations\Version20191030113307' --no-interaction
```


3. Reimport the data back:

```
mysql -uroot -p davis < baikal_to_davis.sql
```

4. Run the necessary remaining migrations:

```
bin/console doctrine:migrations:migrate
```

# Access / Webserver

A simple status page is available on the root `/` of the server.

The administration interface is available at `/dashboard`. You need to login to use it (See `ADMIN_LOGIN` and `ADMIN_PASSWORD` env vars).

The main endpoint for CalDAV, WebDAV or CardDAV is at `/dav`.

> Note: For shared hosting, the `symfony/apache-pack` is included and provides a standard `.htaccess` file in the public directory so redirections should work out of the box.

### Example Caddy 2 configuration

    dav.domain.tld {
        # General settings
        encode zstd gzip
        header {
            -Server
            -X-Powered-By

            # enable HSTS
            Strict-Transport-Security max-age=31536000;

            # disable clients from sniffing the media type
            X-Content-Type-Options nosniff

            # keep referrer data off of HTTP connections
            Referrer-Policy no-referrer-when-downgrade
        }

        root * /var/www/davis/public
        php_fastcgi 127.0.0.1:8000
        file_server
    }

### Example Apache 2.4 configuration

    <VirtualHost *:80>
        ServerName dav.domain.tld

        DocumentRoot /var/www/davis/public
        DirectoryIndex /index.php

        <Directory /var/www/davis/public>
            AllowOverride None
            Order Allow,Deny
            Allow from All
            FallbackResource /index.php
        </Directory>

        # Apache > 2.4.25, else remove this part
        <Directory /var/www/davis/public/bundles>
            FallbackResource disabled
        </Directory>

        # Env vars (if you did not use .env.local)
        SetEnv APP_ENV prod
        SetEnv APP_SECRET <app-secret-id>
        SetEnv DATABASE_DRIVER "mysql"
        SetEnv DATABASE_URL "mysql://db_user:db_pass@host:3306/db_name?serverVersion=mariadb-10.6.10&charset=utf8mb4"
        # ... etc
    </VirtualHost>

### Example Nginx configuration

    server {
        server_name dav.domain.tld;
        root /var/www/davis/public;

        location / {
            try_files $uri /index.php$is_args$args;
        }

        location /bundles {
            try_files $uri =404;
        }

        location ~ ^/index\.php(/|$) {
            fastcgi_pass unix:/var/run/php/php7.2-fpm.sock; # Change for your PHP version
            fastcgi_split_path_info ^(.+\.php)(/.*)$;
            include fastcgi_params;

            # Env vars (if you did not use .env.local)
            fastcgi_param APP_ENV prod;
            fastcgi_param APP_SECRET <app-secret-id>;
            fastcgi_param DATABASE_DRIVER "mysql"
            fastcgi_param DATABASE_URL "mysql://db_user:db_pass@host:3306/db_name?serverVersion=mariadb-10.6.10&charset=utf8mb4";
            # ... etc ...

            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            fastcgi_param DOCUMENT_ROOT $realpath_root;
            internal;
        }

        location ~ \.php$ {
            return 404;
        }
    }

More examples and information [here](https://symfony.com/doc/current/setup/web_server_configuration.html).

## Well-known redirections for CalDAV and CardDAV

Web-based protocols like CalDAV and CardDAV can be found using a discovery service. Some clients require that you implement a path prefix to point to the correct location for your service. See [here](https://en.wikipedia.org/wiki/List_of_/.well-known/_services_offered_by_webservers) for more info.

If you use Apache as your webserver, you can enable the redirections with:

    RewriteEngine On
    RewriteRule ^\.well-known/carddav /dav/ [R=301,L]
    RewriteRule ^\.well-known/caldav /dav/ [R=301,L]

Make sure that `mod_rewrite` is enabled on your installation beforehand.

If you use Nginx, you can add this to your configuration:

    location / {
       rewrite ^/.well-known/carddav /dav/ redirect;
       rewrite ^/.well-known/caldav /dav/ redirect;
    }

# Dockerized installation

A `Dockerfile` is available for you to compile the image.

To build the checked out version, just run:

    docker build --pull --file docker/Dockerfile --tag davis:latest .

This will build a `davis:latest` image that you can directly use. Do not forget to pass sensible environment variables to the container since the _dist_ `.env` file will take precedence if no `.env.local` or environment variable is found.

> You can also use the release image created on the Github container registry: ghcr.io/tchapi/davis:edge

## Full stack

A `docker-compose.yml` file is also included (in the `docker` folder) as a minimal example setup with a MariaDB database and Nginx as a reverse proxy.

You can start the containers with :

    cd docker && docker-compose up -d

> **Note**
>
> The recipe above uses MariaDB but you can also use PostgreSQL with:
> ```
> cd docker && docker-compose -f docker-compose-postgresql.yml up -d
> ```

**⚠ Do not forget to run all the migrations the first time you run the container** :

    docker exec -it davis sh -c "APP_ENV=prod bin/console doctrine:migrations:migrate --no-interaction"

### Updating from a previous version

If you update the code, you need to make sure the database structure is in sync.

**Before v3.0.0**, you need to force the update:

    docker exec -it davis sh -c "APP_ENV=prod bin/console doctrine:schema:update --force --no-interaction"

**For v3.0.0 and after**, you can just migrate again (_provided you correctly followed the migration notes in the v3.0.0 release_):

    docker exec -it davis sh -c "APP_ENV=prod bin/console doctrine:migrations:migrate --no-interaction"


Then, head up to `http://<YOUR_DOCKER_IP>:9000` to see the status display :

![Status page](_screenshots/status.png)

> Note that there is no user and no principals created by default.

# Development

You can spin off a local PHP webserver with:

    php -S localhost:8000 -t public

If you change or add translations, you need to update the `messages` XLIFF file with:

    bin/console translation:extract en --force --domain=messages+intl-icu

## Testing

You can use:

    ./bin/phpunit

## Code linting

We use [PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer) with:

    PHP_CS_FIXER_IGNORE_ENV=True ./vendor/bin/php-cs-fixer fix

# Libraries used

  - Symfony 5 (Licence : MIT)
  - Sabre-io/dav (Licence : BSD-3-Clause)
  - Bootstrap 4 (Licence : MIT)

_This project does not use any pipeline for the assets since the frontend side is relatively simple, and based on Bootstrap._

# Licence

This project is release under the MIT licence. See the LICENCE file

[ci_badge]: https://github.com/tchapi/davis/workflows/CI/badge.svg
[ci_link]: https://github.com/tchapi/davis/actions?query=workflow%3ACI

[sponsor_badge]: https://img.shields.io/badge/sponsor%20me-🙏-blue?logo=paypal
[sponsor_link]: https://paypal.me/tchap

[release_badge]: https://img.shields.io/github/v/release/tchapi/davis
[release_link]: https://github.com/tchapi/davis/releases