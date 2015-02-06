<?php

use Silex\Application;
use Silex\Provider\FormServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ValidatorServiceProvider;

$app = new Application();

$app->register(new FormServiceProvider());
$app->register(new ServiceControllerServiceProvider());
$app->register(new SessionServiceProvider(), array(
    'session.storage.options' => array(
        'name' => 'session',
    ),
));
$app->register(new TranslationServiceProvider(), array(
    'translator.messages' => array(),
));
$app->register(new TwigServiceProvider(), array(
    'twig.class_path' => __DIR__ . '/vendor/twig/lib',
    'twig.form.templates'   => array(
        'forms.twig',
    ),
    'twig.path' => __DIR__.'/../templates',
));
$app->register(new UrlGeneratorServiceProvider());
$app->register(new ValidatorServiceProvider());

$app['api'] = Cpanel_PublicAPI::getInstance(array(
    'service' => array(
        'cpanel' => array(
            'config' => array(
                'host' => $GLOBALS['parameters']['whm']['hostname'],
                'user' => $GLOBALS['parameters']['whm']['username'],
                'password' => $GLOBALS['parameters']['whm']['password'],
            ),
        ),
        'whm' => array(
            'config' => array(
                'host' => $GLOBALS['parameters']['whm']['hostname'],
                'user' => $GLOBALS['parameters']['whm']['username'],
                'password' => $GLOBALS['parameters']['whm']['password'],
            ),
        ),
    ),
));

$app['stash'] = new Stash\Pool(new Stash\Driver\FileSystem(array(
    'path' => __DIR__.'/../var/stash',
)));

$app['twig'] = $app->share($app->extend(
    'twig',
    function($twig, $app) {
        $twig->addExtension(new Twig_Extensions_Extension_Debug());
        $twig->addGlobal('year', date('Y'));

        return $twig;
    }
));

return $app;
