<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

use App\Commands\Planning\CreateDefaultInput as PlanningCreateDefaultInput;
use App\Commands\Planning\Create as PlanningCreate;
use App\Commands\UpdateSitemap;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions(
        [
            "app:create-default-planning-input" => function (ContainerInterface $container) {
                return new PlanningCreateDefaultInput($container);
            },
            "app:create-plannings" => function (ContainerInterface $container) {
                return new PlanningCreate($container);
            },
            "app:update-sitemap" => function (ContainerInterface $container) {
                return new UpdateSitemap($container);
            }
        ]
    );
};