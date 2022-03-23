<?php

declare(strict_types=1);

use App\Commands\BackupSponsorImages;
use App\Commands\Listing as ListingCommand;
use App\Commands\Pdf\Create as PdfCreate;
use App\Commands\Pdf\Validate as PdfValidate;
use App\Commands\Planning\Create as PlanningCreate;
use App\Commands\Planning\Input\Recalculate as RecalculatePlanningInput;
use App\Commands\Planning\Recreate as PlanningRecreate;
use App\Commands\Planning\Report as PlanningReport;
use App\Commands\Planning\RetryTimeout as PlanningRetryTimeout;
use App\Commands\Planning\Validator as PlanningValidator;
use App\Commands\RemoveOld;
use App\Commands\Schedule\Create as ScheduleCreate;
use App\Commands\Schedule\Get as ScheduleGet;
use App\Commands\UpdateSitemap;
use App\Commands\Validator;
use Psr\Container\ContainerInterface;

$commands = [
    "app:recalculate-planning-inputs" => function (ContainerInterface $container): RecalculatePlanningInput {
        return new RecalculatePlanningInput($container);
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
    "app:recreate-planning" => function (ContainerInterface $container): PlanningRecreate {
        return new PlanningRecreate($container);
    },
    "app:retry-timeout-planning" => function (ContainerInterface $container): PlanningRetryTimeout {
        return new PlanningRetryTimeout($container);
    },
    "command-planning-report" => function (ContainerInterface $container): PlanningReport {
        return new PlanningReport($container);
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
    "app:remove-old-tournaments" => function (ContainerInterface $container): RemoveOld {
        return new RemoveOld($container);
    },
    "app:create-pdf" => function (ContainerInterface $container): PdfCreate {
        return new PdfCreate($container);
    },
    "app:validate-pdf" => function (ContainerInterface $container): PdfValidate {
        return new PdfValidate($container);
    }
];

$commands["app:list"] = function (ContainerInterface $container) use ($commands): ListingCommand {
    return new ListingCommand($container, array_keys($commands));
};

return $commands;
