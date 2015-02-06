<?php

use Silex\Provider\MonologServiceProvider;
use Silex\Provider\WebProfilerServiceProvider;

require __DIR__.'/prod.php';

$app['debug'] = true;

$app->register(new MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../var/logs/dev.log',
));

$profiler = new WebProfilerServiceProvider();
$app->register($profiler, array(
    'profiler.cache_dir' => __DIR__.'/../var/cache/profiler',
));
$app->mount('/_profiler', $profiler);

$app['twig.options'] = array(
    'cache' => false,
    'debug' => $app['debug'],
);
$app['twig.loader.filesystem']->addPath(
    __DIR__.'/../vendor/symfony/web-profiler-bundle/Symfony/Bundle/WebProfilerBundle/Resources/views',
    'WebProfiler'
);
