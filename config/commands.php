<?php

declare(strict_types=1);

use App\Commands\BackupImages;
use App\Commands\Listing as ListingCommand;
use App\Commands\Pdf\Create as PdfCreate;
use App\Commands\Pdf\Validate as PdfValidate;
use App\Commands\RemoveOld;
use App\Commands\UpdateSitemap;
use App\Commands\Validator;
use Psr\Container\ContainerInterface;

$commands = [
    "app:update-sitemap" => function (ContainerInterface $container): UpdateSitemap {
        return new UpdateSitemap($container);
    },
    "app:backup-images" => function (ContainerInterface $container): BackupImages {
        return new BackupImages($container);
    },
    "app:validate" => function (ContainerInterface $container): Validator {
        return new Validator($container);
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
