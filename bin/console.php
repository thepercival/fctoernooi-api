#!/usr/bin/env php
<?php

$rootPath = realpath(__DIR__ . '/..');
require $rootPath . '/vendor/autoload.php';

use DI\ContainerBuilder;
use Symfony\Component\Console\Application;
use Slim\Factory\AppFactory;
use App\Commands\Planning\CreateDefaultInput as PlanningCreateDefaultInput;

// Set the absolute path to the root directory.

try {
    // Instantiate PHP-DI ContainerBuilder
    $containerBuilder = new ContainerBuilder();
    // Set up settings
    $settings = require __DIR__ . '/../conf/settings.php';
    $containerBuilder->addDefinitions($settings);
// Set up dependencies
    $dependencies = require __DIR__ . '/../conf/dependencies.php';
    $dependencies($containerBuilder);
// Set up repositories
    $repositories = require __DIR__ . '/../conf/repositories.php';
    $repositories($containerBuilder);
// Set up commands
    $commands = require __DIR__ . '/../conf/commands.php';
    $commands($containerBuilder);
// Build PHP-DI Container instance
    $container = $containerBuilder->build();

    $command = null;
    if (array_key_exists(1, $argv) === false) {
        throw new \Exception("add a parameter with the actionname", E_ERROR);
    }

    $app = new Application();
    $command = $container->get($argv[1]);
    $app->add($command);
    $app->run();
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}

