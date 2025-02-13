<?php

if (false !== getenv('ENV_DIR') && '' !== getenv('ENV_DIR')) {
    $_SERVER['APP_RUNTIME_OPTIONS']['dotenv_path'] = getenv('ENV_DIR').'/.env';
}

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    $env_dir = false != getenv('ENV_DIR') ? getenv('ENV_DIR') : dirname(__DIR__);
    (new Dotenv())->bootEnv($env_dir.'/.env');

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
