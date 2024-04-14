<?php

declare(strict_types=1);

use App\Commands\BackupImagesCommand;
use App\Commands\BestPlanningCreatedCommand;
use App\Commands\ListingCommand;
use App\Commands\Pdf\PdfCreateCommand;
use App\Commands\Pdf\PdfValidateCommand;
use App\Commands\RemoveOldTournamentsCommand;
use App\Commands\UpdateSitemapCommand;
use App\Commands\StructureAdminCommand;
use JMS\Serializer\SerializerInterface;
use Psr\Container\ContainerInterface;

$commands = [
    "app:update-sitemap" => function (ContainerInterface $container): UpdateSitemapCommand {
        return new UpdateSitemapCommand($container);
    },
    "app:backup-images" => function (ContainerInterface $container): BackupImagesCommand {
        return new BackupImagesCommand($container);
    },
    "app:structure-admin" => function (ContainerInterface $container): StructureAdminCommand {
        return new StructureAdminCommand($container);
    },
    "app:remove-old-tournaments" => function (ContainerInterface $container): RemoveOldTournamentsCommand {
        return new RemoveOldTournamentsCommand($container);
    },
    "app:create-pdf" => function (ContainerInterface $container): PdfCreateCommand {
        return new PdfCreateCommand($container);
    },
    "app:validate-pdf" => function (ContainerInterface $container): PdfValidateCommand {
        return new PdfValidateCommand($container);
    },
    "app:planning-available-listener" => function (ContainerInterface $container, SerializerInterface $serializer): BestPlanningCreatedCommand {
        return new BestPlanningCreatedCommand($container, $serializer);
    }

];

$commands["app:list"] = function (ContainerInterface $container) use ($commands): ListingCommand {
    return new ListingCommand($container, array_keys($commands));
};

return $commands;
