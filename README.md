Davis
---

[![Build Status][ci_badge]][ci_link]
[![Publish Docker image](https://github.com/tchapi/davis/actions/workflows/main.yml/badge.svg?branch=main)](https://github.com/tchapi/davis/actions/workflows/main.yml)
[![Latest release][release_badge]][release_link]
[![License](https://img.shields.io/github/license/tchapi/davis)](https://github.com/tchapi/davis/blob/main/LICENSE)
![Platform](https://img.shields.io/badge/platform-amd64%20%7C%20arm64-blue?logo=docker)
![PHP Version](https://img.shields.io/badge/php-8.2%20%7C%208.3%20%7C%208.4-777BB4?logo=php&logoColor=white)
[![Sponsor me][sponsor_badge]][sponsor_link]

A modern, simple, feature-packed, fully translatable DAV server, admin interface and frontend based on `sabre/dav`, built with [Symfony 7](https://symfony.com/) and [Bootstrap 5](https://getbootstrap.com/), initially inspired by [Baïkal](https://github.com/sabre-io/Baikal) (_see dependencies table below for more detail_)

### Web admin dashboard

Provides user edition, calendar creation and sharing, and address book creation. The interface is simple and straightforward, responsive, and provides a light and a dark mode.

Supports **Basic authentication**, as well as **IMAP** and **LDAP** (_via external providers_).

### DAV Server

The underlying server implementation supports (*non-exhaustive list*) CalDAV, CardDAV, WebDAV, calendar sharing, scheduling, mail notifications, and server-side subscriptions (*depending on the capabilities of the client*).

### Additional features ✨

- Subscriptions (to be added via the client, such as the macOS calendar, for instance)
- Public calendars, available to anyone with the link
- Automatic birthday calendar, updated on the fly when birthdates change in your contacts

### Deployment

Easily containerisable (_`Dockerfile` and sample `docker-compose` configuration file provided_).

NixOS [package](https://search.nixos.org/packages?channel=unstable&show=davis&from=0&size=50&sort=relevance&type=packages&query=davis) and module available.

Comes with already built Docker images in two flavours: [standalone](https://github.com/tchapi/davis/pkgs/container/davis-standalone) (with included Caddy reverse proxy) or [barebone](https://github.com/tchapi/davis/pkgs/container/davis).

- - -

✨ Created and maintained (with the help of the community) by [@tchapi](https://github.com/tchapi). ✨

![Dashboard page](_screenshots/dashboard.png)
![User creation page](_screenshots/user.png)
![Sharing page](_screenshots/sharing.png)

| Dark / Light mode  | Useful information at hand        |
|--------------------|----------------------------|
| ![Color mode](_screenshots/mode.png)| ![Setup information](_screenshots/setup_info.png)|

# 🔩 Requirements

  - PHP > 8.2 (with `pdo_mysql` [or `pdo_pgsql`, `pdo_sqlite`], `gd` and `intl` extensions), compatible up to PHP 8.3 (_See dependencies table below_)
  - A compatible database layer, such as MySQL or MariaDB (recommended), PostgreSQL (not extensively tested yet) or SQLite (not extensively tested yet)
  - Composer > 2 (_The last release compatible with Composer 1 is [v1.6.2](https://github.com/tchapi/davis/releases/tag/v1.6.2)_)
  - The [`imap`](https://www.php.net/manual/en/imap.installation.php) and [`ldap`](https://www.php.net/manual/en/ldap.installation.php) PHP extensions if you want to use either authentication methods (_these are not enabled / compiled by default except in the Docker image_)

Dependencies
------------

| Release            | Status                     | PHP version        |
|--------------------|----------------------------|--------------------|
| `main` (edge)      | development branch         | PHP 8.2+           |
| `v5.x`             | stable                     | PHP 8.2+           |
| `v4.x`             | security fixes only        | PHP 8.0 → 8.3      |
| `v3.x`             | :warning: unmaintained     | PHP 7.3 → 8.2      |

# 🧰 Installation

0. Clone this repository

1. Retrieve the dependencies:

    a. If you plan to run Davis locally, for development purposes

    ```
    composer install
    ```

    b. If you plan to run Davis on production

    ```
    composer install --no-dev
    ```

   And set `APP_ENV=prod` in your `.env.local` file (see below)


3. At least put the correct credentials to your database (driver and url) in your `.env.local` file so you can easily create the necessary tables.

4. Run the migrations to create all the necessary tables:

    ```
    bin/console doctrine:migrations:migrate
    ```

**Davis** can also be used with a pre-existing MySQL database (_for instance, one previously managed by Baïkal_). See the paragraph "Migrating from Baikal" for more info.

> [!NOTE]
>
> The tables are not _exactly_ equivalent to those of Baïkal, and allow for a bit more room in columns for instance (among other things)

## Configuration

Create your own `.env.local` file to change the necessary variables, if you plan on using `symfony/dotenv`.

> [!NOTE]
>
> If your installation is behind a web server like Apache or Nginx, you can setup the env vars directly in your Apache or Nginx configuration (see below). Skip this part in this case.

> [!CAUTION]
>
> In a production environnement, the `APP_ENV` variable MUST be set to `prod` to prevent leaking sensitive data.

**a. The database driver and url** (_you should already have it configured since you created the database previously_)
    
```shell
DATABASE_DRIVER=mysql # or postgresql, or sqlite
DATABASE_URL=mysql://db_user:db_pass@host:3306/db_name?serverVersion=10.9.3-MariaDB&charset=utf8mb4
```

**b. The admin password for the backend**

```shell
ADMIN_LOGIN=admin
ADMIN_PASSWORD=test
```

> [!NOTE]
>
> You can bypass auth entirely if you use a third party authorization provider such as Authelia. In that case, set the `ADMIN_AUTH_BYPASS` env var to `true` (case-sensitive, this is actually the string `true`, not a boolean) to allow full access to the dashboard. This does not change the behaviour of the DAV server.

**c. The auth Realm and method for HTTP auth**

```shell
AUTH_REALM=SabreDAV
AUTH_METHOD=Basic # can be "Basic", "IMAP" or "LDAP"
```
> See [the following paragraph](#specific-environment-variables-for-imap-and-ldap-authentication-methods) for more information if you choose either IMAP or LDAP.

**d. The global flags to enable CalDAV, CardDAV and WebDAV**. You can also disable the option to have calendars public

```shell
CALDAV_ENABLED=true
CARDDAV_ENABLED=true
WEBDAV_ENABLED=false

PUBLIC_CALENDARS_ENABLED=true
```

> [!NOTE]
>
> By default, `PUBLIC_CALENDARS_ENABLED` is true. That doesn't mean that all calendars are public by default — it just means that you have an option, upon calendar creation, to set the calendar public (but it's not public by default).


**e. Mailer configuration**

It includes:
  - the mailer uri (`MAILER_DSN`) 
  - The email address that your invites are going to be sent from

```shell
MAILER_DSN=smtp://user:pass@smtp.example.com:port
INVITE_FROM_ADDRESS=no-reply@example.org
```

> [!WARNING]
> If the username, password or host contain any character considered special in a URI (such as `: / ? # [ ] @ ! $ & ' ( ) * + , ; =`), you MUST encode them.
> See [here](https://symfony.com/doc/current/mailer.html#transport-setup) for more details.

**f. The reminder offset for all birthdays**

You must specify a relative duration, as specified in [the RFC 5545 spec](https://www.rfc-editor.org/rfc/rfc5545.html#section-3.3.6)

```shell
BIRTHDAY_REMINDER_OFFSET=PT9H
```

If you don't want a reminder for birthday events, set it to the `false` value (lowercase):

```shell
BIRTHDAY_REMINDER_OFFSET=false
```

> [!NOTE]
>
> By default, if the env var is not set or empty, we use `PT9H` (9am on the date of the birthday).

**g. The paths for the WebDAV installation**

> [!TIP]
>
> I recommend that you use absolute directories so you know exactly where your files reside.

```shell
WEBDAV_TMP_DIR=/webdav/tmp
WEBDAV_PUBLIC_DIR=/webdav/public
WEBDAV_HOMES_DIR=
```

> [!NOTE]
>
> In a docker setup, I recommend setting `WEBDAV_TMP_DIR` to `/tmp`.

> [!NOTE]
>
> By default, home directories are disabled totally (the env var is set to an empty string). If needed, it is recommended to use a folder that is **NOT** a child of the public dir, such as `/webdav/homes` for instance, so that users cannot access other users' homes.

**h. The log file path**

You can use an absolute file path here, and you can use Symfony's `%kernel.logs_dir%` and `%kernel.environment%` placeholders if needed (as in the default value). Setting it to `/dev/null` will disable logging altogether.

```shell
LOG_FILE_PATH="%kernel.logs_dir%/%kernel.environment%.log"
```

**i. The timezone you want for the app**

This must comply with the [official list](https://www.php.net/manual/en/timezones.php)

```shell
APP_TIMEZONE=Australia/Lord_Howe
```

> Set a void value like so:
> ```shell
> APP_TIMEZONE=
> ```
> in your environment file if you wish to use the **actual default timezone of the server**, and not enforcing it. 

**j. Trusting forwarded headers**

If you're behind one or several proxies, the TLS termination might be upstream and the application might not be aware of the HTTPS context. In order for urls to be generated with the correct scheme, you should indicate that you trust the chain of proxies until the TLS termination one. You can use the Symfony mechanism for that (see [documentation](https://symfony.com/doc/7.2/deployment/proxies.html) for possible values):

```shell
SYMFONY_TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR
```

#### Overriding the dotenv (`.env`) path

You can override the expected location of the environment files (`.env`, `.env.local`, etc) by setting the `ENV_DIR` variable.

The value should be to a _folder_ containing the env files. This value must be specified in the actual environment and *not* in an `.env` file as it is read and evaluated **before** the env files are read.

For instance, you can use it to call `bin/console` with a specific dotenv directory:

```shell
> ENV_DIR=/var/lib/davis bin/console
```

Or use it directly in the Apache configuration

```apache
<VirtualHost *:80>
    # .. rest of config (see ¶ below)

    SetEnv ENV_DIR /var/lib/davis
    # ... other env vars if needed
</VirtualHost>
```

### Specific environment variables for IMAP and LDAP authentication methods

In case you use the `IMAP` auth type, you must specify the auth url (_the "mailbox" url_) in `IMAP_AUTH_URL` as `host:port`, the encryption method (SSL, TLS or None) and whether the certificate should be validated.

You should also explicitely define whether you want new authenticated users to be created upon login:

```shell
IMAP_AUTH_URL=imap.mydomain.com:993
IMAP_ENCRYPTION_METHOD=ssl # ssl, tls or false
IMAP_CERTIFICATE_VALIDATION=true
IMAP_AUTH_USER_AUTOCREATE=true # false by default
```

Same goes for LDAP, where you must specify the LDAP server url, the DN pattern, the Mail attribute, as well as whether you want new authenticated users to be created upon login (_like for IMAP_):

```shell
LDAP_AUTH_URL=ldap://127.0.0.1:3890 # default LDAP port
LDAP_DN_PATTERN=uid=%u,ou=users,dc=domain,dc=com
LDAP_MAIL_ATTRIBUTE=mail
LDAP_AUTH_USER_AUTOCREATE=true # false by default
LDAP_CERTIFICATE_CHECKING_STRATEGY=try # try by default. Other values are: never, hard, demand or allow
```

> Ex: for [Zimbra LDAP](https://zimbra.github.io/adminguide/latest/#zimbra_ldap_service), you might want to use the `zimbraMailDeliveryAddress` attribute to retrieve the principal user email:
>    ```shell
>    LDAP_MAIL_ATTRIBUTE=zimbraMailDeliveryAddress
>    ```

## Migrating from Baïkal?

If you're migrating from Baïkal, then you will likely want to do the following :

1. Get a backup of your data (without the `CREATE`  statements, but with complete `INSERT`  statements):

    ```shell
    mysqldump -u root -p --no-create-info --complete-insert baikal > baikal_to_davis.sql # baikal is the actual name of your database
    ```

2. Create a new database for Davis (let's name it `davis`) and create the base schema:

    ```shell
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

> [!NOTE]
> Some details / steps to resolve are also available in https://github.com/tchapi/davis/issues/226.

# 🌐 Access / Webserver

A simple status page is available on the root `/` of the server.

The administration interface is available at `/dashboard`. You need to login to use it (See `ADMIN_LOGIN` and `ADMIN_PASSWORD` env vars).

The main endpoint for CalDAV, WebDAV or CardDAV is at `/dav`.

> [!TIP]
>
> For shared hosting, the `symfony/apache-pack` is included and provides a standard `.htaccess` file in the public directory so redirections should work out of the box.

## API Endpoint

For user and calendar management there is an API endpoint. See [the API documentation](docs/api/README.md) for more information.

> [!TIP]
>
> The API endpoint requires an environment variable `API_KEY` set to a secret key that you will use in the `X-Davis-API-Token` header of your requests to authenticate. You can generate it with `bin/console api:generate`

## Webserver Configuration Examples

### Example Caddy 2 configuration

```
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
```
### Example Apache 2.4 configuration

```apache
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
    SetEnv DATABASE_URL "mysql://db_user:db_pass@host:3306/db_name?serverVersion=10.9.3-MariaDB&charset=utf8mb4"
    # ... etc
</VirtualHost>
```

### Example Nginx configuration

```nginx
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
        fastcgi_param DATABASE_DRIVER "mysql";
        fastcgi_param DATABASE_URL "mysql://db_user:db_pass@host:3306/db_name?serverVersion=10.9.3-MariaDB&charset=utf8mb4";
        # ... etc ...

        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }
}
```

More examples and information [here](https://symfony.com/doc/current/setup/web_server_configuration.html).

## Well-known redirections for CalDAV and CardDAV

Web-based protocols like CalDAV and CardDAV can be found using a discovery service. Some clients require that you implement a path prefix to point to the correct location for your service. See [here](https://en.wikipedia.org/wiki/List_of_/.well-known/_services_offered_by_webservers) for more info.

If you use Apache as your webserver, you can enable the redirections with:

```apache
RewriteEngine On
RewriteRule ^\.well-known/carddav /dav/ [R=301,L]
RewriteRule ^\.well-known/caldav /dav/ [R=301,L]
```

Make sure that `mod_rewrite` is enabled on your installation beforehand.

If you use Nginx, you can add this to your configuration:

```nginx
location / {
    rewrite ^/.well-known/carddav /dav/ redirect;
    rewrite ^/.well-known/caldav /dav/ redirect;
}
```

# 🐳 Dockerized installation

A `Dockerfile` is available for you to compile the image.

To build the checked out version, just run:

    docker build --pull --file docker/Dockerfile --tag davis:latest --build-arg fpm_user=82:82 .

> [!TIP]
> 
> The `fpm_user` build arg allows to set:
>  - the uid FPM will run with
>  - the owner of the app folder
>
> This is helpful if you have a proxy that does not use the same default PHP Alpine uid/gid for www-data (82:82). For instance, in the docker compose file, nginx uses 101:101
>

This will build a `davis:latest` image that you can directly use. Do not forget to pass sensible environment variables to the container since the _dist_ `.env` file will take precedence if no `.env.local` or environment variable is found.

You can use `--platform` to specify the platform to build for. Currently, `arm64` (ARMv8) and `amd64` (x86) are supported.

> [!IMPORTANT]
> 
> ⚠ Do not forget to run all the database migrations the first time you run the container :
>
>     docker exec -it davis sh -c "APP_ENV=prod bin/console doctrine:migrations:migrate --no-interaction"

## Docker images

For each release, a Docker image is built and published in the [Github package repository](https://github.com/tchapi/davis/pkgs/container/davis).

### Release images

Each release builds and tags two images: one for the standard build (no reverse-proxy) and one for the standalone build (including Caddy as a reverse-proxy). Example:

```
docker pull ghcr.io/tchapi/davis:v4.4.0
```

```
docker pull ghcr.io/tchapi/davis-standalone:v4.4.0
```

### Edge image

The edge image is generally built from the tip of the main branch, but might sometimes be used for specific branch testing:

```
docker pull ghcr.io/tchapi/davis:edge
```

> [!WARNING]
> 
> The `edge` image must not be considered stable. **Use only release images for production setups**.

## Full stack

A few `docker-compose.yml` files are also included (in the `docker` folder) as minimal example setups, with various databases for instance.

You can start the containers with :

    cd docker && docker compose up -d

> [!NOTE]
>
> The default recipe above uses MariaDB.

> [!IMPORTANT]
> 
> ⚠ Do not forget to run all the database migrations the first time you run the container :
>
>     docker exec -it davis sh -c "APP_ENV=prod bin/console doctrine:migrations:migrate --no-interaction"

> [!WARNING]
> 
> For SQLite, you must also make sure that the folder the database will reside in AND the database file in itself have the right permissions! You can do for instance:
> `chown -R www-data: /data` if `/data` is the folder your SQLite database will be in, just after you have run the migrations

### Updating from a previous version

If you update the code, you need to make sure the database structure is in sync.

**Before v3.0.0**, you need to force the update:

    docker exec -it davis sh -c "APP_ENV=prod bin/console doctrine:schema:update --force --no-interaction"

**For v3.0.0 and after**, you can just migrate again (_provided you correctly followed the migration notes in the v3.0.0 release_):

    docker exec -it davis sh -c "APP_ENV=prod bin/console doctrine:migrations:migrate --no-interaction"


Then, head up to `http://<YOUR_DOCKER_IP>:9000` to see the status display :

![Status page](_screenshots/status.png)

> Note that there is no user and no principals created by default.

# NixOS Installation

To install Davis on NixOS, you can use the builtin NixOS module [`services.davis`](https://search.nixos.org/options?channel=unstable&query=services.davis).

Currently the NixOS module and package are in the nixos-unstable channel, but they are slated to enter the stable channel in the 24.05 release.

* [All `services.davis` options](https://search.nixos.org/options?channel=unstable&query=services.davis)
* [Basic Guide](https://nixos.org/manual/nixos/unstable/#module-services-davis)

If you encounter a bug or problem with the NixOS Davis module please open an issue [at the nixpkgs repo](https://github.com/NixOS/nixpkgs/issues/new/choose) so the module maintainers can assist.

# Development

You can spin off a local PHP webserver with:

    php -S localhost:8000 -t public

If you change or add translations, you need to update the `messages` XLIFF file with:

    bin/console translation:extract en --force --domain=messages+intl-icu

## Testing

You can use:

    ./bin/phpunit

## ✨ Code linting

We use [PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer) with:

    PHP_CS_FIXER_IGNORE_ENV=True ./vendor/bin/php-cs-fixer fix

## ❓ How-to's

Below are some issues that can bring more info / insight into custom setups that Davis users have experienced in the past. Hopefully it can help:

  - **Davis on Proxmox / TrueNAS Scale**: https://github.com/tchapi/davis/issues/164


## 🐛 Troubleshooting

Depending on how you run Davis, logs are either:
  - [dev] printed out directly in the console
  - [dev] available in the Symfony Debug Bar in the [Profiler](https://symfony.com/doc/current/profiler.html)
  - [dev] logged in `./var/log/dev.log`
  - [prod] logged in `./var/log/prod.log`, but only if there has been an error (_it's the fingers_crossed filter, explained [here](https://symfony.com/doc/current/logging.html#handlers-that-modify-log-entries)_)

> [!NOTE]
>
> It's `./var/log` (relative to the Davis installation), not `/var/log`.
>
> To tail the aplication log on Docker, do:
> ```
> docker exec -it davis tail /var/www/davis/var/log/prod.log
> ```

### I have a "Bad timezone configuration env var" error on the dashboard

If you see this:

![Bad timezone configuration env var error](_screenshots/bad_timezone_configuration_env_var.png)

It means that the value you set for the `APP_TIMEZONE` env var is not a correct timezone, as per [the official list](https://www.php.net/manual/en/timezones.php). Your timezone has thus not been set and is the server's default (Here, UTC). Adjust the setting accordingly.

### I have a 500 and no tables have been created

You probably forgot to run the migration once to create the necessary DB schema

In Docker:

```shell
docker exec -it davis sh -c "APP_ENV=prod bin/console doctrine:migrations:migrate --no-interaction"
```

In a shell, if you run Davis locally:

    bin/console doctrine:migrations:migrate

### I have a 500 and a log about `Uncaught Error: Class "Symfony\Bundle\WebProfilerBundle\WebProfilerBundle" not found`

You are running the app in dev mode, but you haven't installed the dev dependencies. Either:

a. Set `APP_ENV=prod` in your local env file (See configuration above)

b. Or `composer install` (without the `--no-dev` flag)


### The LDAP connection is not working

> [!NOTE]
>
> Make sure all environment parameters are in plain text (no quotes).

Check if your instance can reach your LDAP server:

  - For Docker instances: make sure it is on the same network
  - Check connection via `ldapsearch`:

    ```shell
    # For docker: connect into container's shell
    docker exec -it davis sh

    # install ldap utils (for alpine linux)
    apk add openldap-clients

    # User checking their own entry
    ldapsearch -H ldap://lldap-server:3890 -D "uid=someuser,ou=users,dc=domain,dc=com" -W -b "dc=domain,dc=com" "(uid=someuser)"
    ```

  - Check that the `LDAP_DN_PATTERN` filter is compliant with your LDAP service
  - Example: `uid=%u,ou=people,dc=domain,dc=com`: [LLDAP](https://github.com/lldap/lldap) uses `people` instead of `users`.

### The birthday calendar is not synced / not up to date

An update event might have been missed. In this case, it's easy to resync all contacts by issuing the command:

```
bin/console dav:sync-birthday-calendar
```

# 📚 Libraries used

  - Symfony 7 (Licence : MIT)
  - Sabre-io/dav (Licence : BSD-3-Clause)
  - Bootstrap 5 (Licence : MIT)

_This project does not use any pipeline for the assets since the frontend side is relatively simple, and based on Bootstrap._

# ⚖️ Licence

This project is release under the MIT licence. See the LICENCE file

[ci_badge]: https://github.com/tchapi/davis/workflows/CI/badge.svg
[ci_link]: https://github.com/tchapi/davis/actions?query=workflow%3ACI

[sponsor_badge]: https://img.shields.io/badge/sponsor%20me-🙏-blue?logo=paypal
[sponsor_link]: https://paypal.me/tchap

[release_badge]: https://img.shields.io/github/v/release/tchapi/davis
[release_link]: https://github.com/tchapi/davis/releases
