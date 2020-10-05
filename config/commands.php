<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;

use App\Commands\Planning\Input\CreateDefaults as PlanningCreateDefaultInput;
use App\Commands\Planning\Create as PlanningCreate;
use App\Commands\Planning\RetryTimeout as PlanningRetryTimeout;
use App\Commands\Listing as ListingCommand;
use App\Commands\UpdateSitemap;
use App\Commands\BackupSponsorImages;
use App\Commands\Validator;
use App\Commands\Planning\Validator as PlanningValidator;

$commands = [
    "app:create-default-planning-input" => function (ContainerInterface $container): PlanningCreateDefaultInput {
        return new PlanningCreateDefaultInput($container);
    },
    "app:create-planning" => function (ContainerInterface $container): PlanningCreate {
        return new PlanningCreate($container);
    },
    "app:retry-timeout-planning" => function (ContainerInterface $container): PlanningRetryTimeout {
        return new PlanningRetryTimeout($container);
    },
    "app:update-sitemap" => function (ContainerInterface $container): UpdateSitemap {
        return new UpdateSitemap($container);
    },
    "app:backup-sponsorimages" => function (ContainerInterface $container): BackupSponsorImages {
        return new BackupSponsorImages($container);
    },
    "app:validate" => function (ContainerInterface $container): Validator {
        return new Validator($container);
    },
    "app:validate-planning" => function (ContainerInterface $container): PlanningValidator {
        return new PlanningValidator($container);
    }
];

$commands["app:list"] = function (ContainerInterface $container) use($commands) : ListingCommand {
    return new ListingCommand($container, array_keys( $commands) );
};

return $commands;
