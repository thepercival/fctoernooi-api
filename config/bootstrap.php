<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\App;

require __DIR__ . '/../vendor/autoload.php';

$containerBuilder = new ContainerBuilder();
// Set up settings
$containerBuilder->addDefinitions(__DIR__ . '/container.php');
$containerBuilder->addDefinitions(__DIR__ . '/repositories.php');
if (isset($_SERVER['REQUEST_METHOD']) === false) {
    $containerBuilder->addDefinitions(__DIR__ . '/commands.php');
}
// Build PHP-DI Container instance
$container = $containerBuilder->build();
/** @var App $app */
$app = $container->get(App::class);
// Register routes
(require __DIR__ . '/routes.php')($app);
// Register middleware
(require __DIR__ . '/middleware.php')($app);

// Init translator instance
// $container->get(Translator::class);

return $app;
