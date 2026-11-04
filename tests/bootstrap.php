<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Create the test database, build its schema and reset the fixtures before each test.
// Note: `--quiet` is needed here for each step so that PHPUnit doesn't fail.
$actions = [
    // No `--if-not-exists`: it asks the platform to list its databases, which SQLite cannot do.
    // Creating an existing database is a no-op here either way, the exit code is ignored below.
    'doctrine:database:create',
    // The schema is built by the migrations, not by `doctrine:schema:update`, so the tests run
    // against the schema an install actually has. The two are not the same on PostgreSQL: DBAL
    // models an identity column without its `DEFAULT nextval(...)`, and the sabre/dav backends
    // INSERT without an id, so every DAV write would fail against a schema:update database.
    'doctrine:migrations:migrate --no-interaction --allow-no-migration',
    'doctrine:fixtures:load --no-interaction',
];

foreach ($actions as $action) {
    passthru(sprintf(
        'APP_ENV=%s php "%s/../bin/console" %s --quiet',
        $_ENV['APP_ENV'],
        __DIR__,
        $action,
    ));
}
