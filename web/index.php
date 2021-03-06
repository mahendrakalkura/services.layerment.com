<?php

if (
    php_sapi_name() === 'cli-server'
    &&
    is_file(__DIR__.preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']))
) {
    return false;
}

if (is_file(__DIR__.'/../parameters.php')) {
    require_once __DIR__.'/../parameters.php';
} else {
    if (is_file(__DIR__.'/../sources/parameters.php')) {
        require_once __DIR__.'/../sources/parameters.php';
    }
}

require_once __DIR__.'/../functions.php';

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../vendor/Cpanel/Util/Autoload.php';

use Symfony\Component\Debug\Debug;

if ($GLOBALS['parameters']['others']['environment'] == 'dev') {
    Debug::enable();
}

$app = require __DIR__.'/../src/app.php';
require __DIR__.sprintf(
    '/../config/%s.php', $GLOBALS['parameters']['others']['environment']
);
require __DIR__.'/../src/controllers.php';
$app->run();
