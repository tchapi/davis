<?php

$overridenEnvDir = getenv('ENV_DIR') ?: null;

if ($overridenEnvDir) {
    // Tell the Runtime not to touch dotenv so we can load our own file.
    // This is needed if the ENV_DIR is outside of the project directory
    $_SERVER['APP_RUNTIME_OPTIONS']['disable_dotenv'] = true;
}

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

if ($overridenEnvDir) {
    // Load our own now, after the runtime has booted
    (new Dotenv())->bootEnv($overridenEnvDir.'/.env');
}

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
