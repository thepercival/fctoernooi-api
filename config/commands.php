<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;

use App\Commands\Planning\CreateDefaultInput as PlanningCreateDefaultInput;
use App\Commands\Planning\Create as PlanningCreate;
use App\Commands\Planning\RetryTimeout as PlanningRetryTimeout;
use App\Commands\UpdateSitemap;
use App\Commands\BackupSponsorImages;
use App\Commands\SendFirstTimeEmail;
use App\Commands\Validator;

return [
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
    "app:send-firsttime-email" => function (ContainerInterface $container): SendFirstTimeEmail {
        return new SendFirstTimeEmail($container);
    },
    "app:validate" => function (ContainerInterface $container): Validator {
        return new Validator($container);
    }
];
