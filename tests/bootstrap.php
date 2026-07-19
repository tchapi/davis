<?php

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Reset the schema and fixtures before each isolated test process. Database
// provisioning belongs to the environment running the suite; SQLite creates
// its database file when Doctrine first connects.
$commands = [
    ['doctrine:schema:drop', '--full-database', '--force', '--quiet'],
    ['doctrine:schema:create', '--quiet'],
    ['doctrine:fixtures:load', '--no-interaction', '--quiet'],
];

foreach ($commands as $command) {
    $process = new Process([
        PHP_BINARY,
        dirname(__DIR__).'/bin/console',
        ...$command,
    ], env: ['APP_ENV' => $_ENV['APP_ENV']]);
    $process->mustRun();
}
