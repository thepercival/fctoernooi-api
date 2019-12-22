<?php
declare(strict_types=1);


use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Tournament;
use FCToernooi\Role\Repository as RoleRepository;
use FCToernooi\Role;
use FCToernooi\User\Repository as UserRepository;
use FCToernooi\User;

use App\Infrastructure\Persistence\User\InMemoryUserRepository;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Voetbal\Sport\Repository as SportRepository;
use Voetbal\Sport;
use Voetbal\Season\Repository as SeasonRepository;
use Voetbal\Season;
use Voetbal\League\Repository as LeagueRepository;
use Voetbal\League;
use Voetbal\Competition\Repository as CompetitionRepository;
use Voetbal\Competition;

return function (ContainerBuilder $containerBuilder) {
    // Here we map our UserRepository interface to its in memory implementation
    $containerBuilder->addDefinitions([
        TournamentRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new TournamentRepository($entityManager ,$entityManager ->getClassMetaData(Tournament::class));
        },
        RoleRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new RoleRepository($entityManager ,$entityManager ->getClassMetaData(Role::class));
        },
        UserRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new UserRepository($entityManager ,$entityManager ->getClassMetaData(User::class));
        },

        SportRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new SportRepository($entityManager ,$entityManager ->getClassMetaData(Sport::class));
        },
        SeasonRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new SeasonRepository($entityManager ,$entityManager ->getClassMetaData(Season::class));
        },
        LeagueRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new LeagueRepository($entityManager ,$entityManager ->getClassMetaData(League::class));
        },
        CompetitionRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new CompetitionRepository($entityManager ,$entityManager ->getClassMetaData(Competition::class));
        },
    ]);
};