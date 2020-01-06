<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Tournament;
use FCToernooi\Role\Repository as RoleRepository;
use FCToernooi\Role;
use FCToernooi\User\Repository as UserRepository;
use FCToernooi\User;
use FCToernooi\Sponsor\Repository as SponsorRepository;
use FCToernooi\Sponsor;

use Voetbal\Sport\Repository as SportRepository;
use Voetbal\Sport;
use Voetbal\Season\Repository as SeasonRepository;
use Voetbal\Season;
use Voetbal\League\Repository as LeagueRepository;
use Voetbal\League;
use Voetbal\Competition\Repository as CompetitionRepository;
use Voetbal\Competition;

use Voetbal\Structure\Repository as StructureRepository;
use Voetbal\Planning\Repository as PlanningRepository;
use Voetbal\Planning;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;
use Voetbal\Planning\Input as PlanningInput;
use Voetbal\Game\Repository as GameRepository;
use Voetbal\Game;
use Voetbal\Field\Repository as FieldRepository;
use Voetbal\Field;
use Voetbal\Referee\Repository as RefereeRepository;
use Voetbal\Referee;
use Voetbal\Sport\Config\Repository as SportConfigRepository;
use Voetbal\Sport\Config as SportConfig;
use Voetbal\Competitor\Repository as CompetitorRepository;
use Voetbal\Competitor;
use Voetbal\Sport\ScoreConfig\Repository as SportScoreConfigRepository;
use Voetbal\Sport\ScoreConfig as SportScoreConfig;

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
        SponsorRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new SponsorRepository($entityManager ,$entityManager ->getClassMetaData(Sponsor::class));
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

        StructureRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new StructureRepository($entityManager);
        },
        PlanningRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new PlanningRepository($entityManager,$entityManager ->getClassMetaData(Planning::class));
        },
        PlanningInputRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new PlanningInputRepository($entityManager ,$entityManager ->getClassMetaData(PlanningInput::class));
        },
        GameRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new GameRepository($entityManager ,$entityManager ->getClassMetaData(Game::class));
        },
        FieldRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new FieldRepository($entityManager ,$entityManager ->getClassMetaData(Field::class));
        },
        RefereeRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new RefereeRepository($entityManager ,$entityManager ->getClassMetaData(Referee::class));
        },
        SportConfigRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new SportConfigRepository($entityManager ,$entityManager ->getClassMetaData(SportConfig::class));
        },
        CompetitorRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new CompetitorRepository($entityManager ,$entityManager ->getClassMetaData(Competitor::class));
        },
        SportScoreConfigRepository::class => function (ContainerInterface $container) {
            $entityManager = $container->get( \Doctrine\ORM\EntityManager::class );
            return new SportScoreConfigRepository($entityManager ,$entityManager ->getClassMetaData(SportScoreConfig::class));
        },
    ]);
};