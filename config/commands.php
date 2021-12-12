<?php

declare(strict_types=1);

use App\Commands\BackupSponsorImages;
use App\Commands\Listing as ListingCommand;
use App\Commands\Pdf\Validator as PdfValidator;
use App\Commands\Planning\Create as PlanningCreate;
use App\Commands\Planning\Input\CreateDefaults as PlanningCreateDefaultInput;
use App\Commands\Planning\RetryTimeout as PlanningRetryTimeout;
use App\Commands\Planning\Validator as PlanningValidator;
use App\Commands\Schedule\Create as ScheduleCreate;
use App\Commands\Schedule\Get as ScheduleGet;
use App\Commands\UpdateSitemap;
use App\Commands\Validator;
use Psr\Container\ContainerInterface;

$commands = [
    "app:create-default-planning-input" => function (ContainerInterface $container): PlanningCreateDefaultInput {
        return new PlanningCreateDefaultInput($container);
    },
    "app:get-schedule" => function (ContainerInterface $container): ScheduleGet {
        return new ScheduleGet($container);
    },
    "app:create-schedule" => function (ContainerInterface $container): ScheduleCreate {
        return new ScheduleCreate($container);
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
    },
    "app:validate-pdf" => function (ContainerInterface $container): PdfValidator {
        return new PdfValidator($container);
    }
];

$commands["app:list"] = function (ContainerInterface $container) use ($commands): ListingCommand {
    return new ListingCommand($container, array_keys($commands));
};

return $commands;
