<?php
declare(strict_types=1);

use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Tournament;
use App\Infrastructure\Persistence\User\InMemoryUserRepository;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

return function (ContainerBuilder $containerBuilder) {
    // Here we map our UserRepository interface to its in memory implementation
    $containerBuilder->addDefinitions([
        TournamentRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new TournamentRepository($entityManager ,$entityManager ->getClassMetaData(Tournament::class));
        },
    ]);
};