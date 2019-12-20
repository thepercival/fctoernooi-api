<?php

use Slim\Factory\AppFactory;
use DI\Container;

if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}

// this line can move to php.settings if allwebsites use it
date_default_timezone_set ( 'UTC' );

require __DIR__ . '/../vendor/autoload.php';
$settings = require __DIR__ . '/../conf/settings.php';

session_start();

// Create Container using PHP-DI
$container = new Container();
$container->set("settings", $settings);

// Set container to create App with on AppFactory
AppFactory::setContainer($container);
$app = AppFactory::create();

// Set up dependencies
require __DIR__ . '/../conf/dependencies.php';

// Register middleware
require __DIR__ . '/../conf/middleware.php';

// Register routes
require __DIR__ . '/../conf/routes.php';

$app->run();

