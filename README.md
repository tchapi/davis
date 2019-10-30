Davis
---

A simple, fully translatable admin interface for `sabre/dav` based on Symfony 4.

# Requirements

PHP > 7.1.3

# Installation

1. Retrieve the dependencies

    composer install

2. Migrate the database

    bin/console migrate

# Development

If you change or add translations, you need to update the `messages` XLIFF file with : 

    bin/console translation:update en --force --domain=messages+intl-icu

# Libraries used

   - Bootstrap 4 (Licence : MIT)

This project does not use any pipeline for the assets since the frontend side is relatively simple, and based on Bootstrap.
