<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

$console = new Application('Application', 'N/A');
$console
    ->register('test')
    ->setCode(
        function (InputInterface $input, OutputInterface $output) use ($app) {
        }
    )
    ->setDescription('test');

return $console;
