#!/bin/sh
set -e

php bin/console doctrine:migrations:migrate --no-interaction

exec "$@"
