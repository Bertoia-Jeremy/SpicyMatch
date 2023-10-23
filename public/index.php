<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    if (getenv('ENV')) {
        (new Dotenv())->overload(dirname(__DIR__).'/.env.'.getenv('ENV'));
    }

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
