<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}


// Create the test database, update the schema and resets the fixture before each test.
// Note: `--quiet` is needed here for each step so that PHPUnit doesn't fail.
$actions = [
    "doctrine:database:create --if-not-exists ",
    "doctrine:schema:update --complete --force",
    "doctrine:fixtures:load --no-interaction"
];

foreach ($actions as $action) {
    passthru(sprintf(
        'APP_ENV=%s php "%s/../bin/console" %s --quiet',
        $_ENV['APP_ENV'],
        __DIR__,
        $action,
    ));
}
