<?php

if (
    php_sapi_name() === 'cli-server'
    &&
    is_file(__DIR__.preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']))
) {
    return false;
}

require_once __DIR__.'/../functions.php';
require_once __DIR__.'/../parameters.php';

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../vendor/Cpanel/Util/Autoload.php';

use Symfony\Component\Debug\Debug;

if (is_mahendra()) {
    Debug::enable();
}

$app = require __DIR__.'/../src/app.php';
require __DIR__.sprintf('/../config/%s.php', get_environment());
require __DIR__.'/../src/controllers.php';
$app->run();
