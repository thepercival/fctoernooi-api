<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Tournament;
use FCToernooi\TournamentUser\Repository as TournamentUserRepository;
use FCToernooi\TournamentUser;
use FCToernooi\Tournament\Invitation\Repository as TournamentInvitationRepository;
use FCToernooi\Tournament\Invitation as TournamentInvitation;
use FCToernooi\User\Repository as UserRepository;
use FCToernooi\User;
use FCToernooi\Sponsor\Repository as SponsorRepository;
use FCToernooi\Sponsor;
use FCToernooi\Competitor\Repository as CompetitorRepository;
use FCToernooi\Competitor;
use FCToernooi\LockerRoom\Repository as LockerRoomRepository;
use FCToernooi\LockerRoom;

use Sports\Planning\Config\Repository as PlanningConfigRepository;
use Sports\Planning\Config as PlanningConfig;
use Sports\Sport\Repository as SportRepository;
use Sports\Sport;
use Sports\Season\Repository as SeasonRepository;
use Sports\Season;
use Sports\League\Repository as LeagueRepository;
use Sports\League;
use Sports\Competition\Repository as CompetitionRepository;
use Sports\Competition;

use Sports\Structure\Repository as StructureRepository;
use SportsPlanning\Repository as PlanningRepository;
use SportsPlanning\Planning;
use SportsPlanning\Input\Repository as PlanningInputRepository;
use SportsPlanning\Input as PlanningInput;
use Sports\Game\Repository as GameRepository;
use Sports\Game;
use Sports\Field\Repository as FieldRepository;
use Sports\Field;
use Sports\Referee\Repository as RefereeRepository;
use Sports\Referee;
use Sports\Sport\Config\Repository as SportConfigRepository;
use Sports\Sport\Config as SportConfig;
use Sports\Sport\ScoreConfig\Repository as SportScoreConfigRepository;
use Sports\Sport\ScoreConfig as SportScoreConfig;
use Sports\Place\Repository as PlaceRepository;
use Sports\Place;
use Sports\Poule\Repository as PouleRepository;
use Sports\Poule;
use Sports\Round\Number\Repository as RoundNumberRepository;
use Sports\Round\Number as RoundNumber;
use Sports\Game\Score\Repository as GameScoreRepository;
use Sports\Game\Score as GameScore;

return [
    TournamentRepository::class => function (ContainerInterface $container): TournamentRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new TournamentRepository($entityManager, $entityManager->getClassMetaData(Tournament::class));
    },
    TournamentUserRepository::class => function (ContainerInterface $container): TournamentUserRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new TournamentUserRepository($entityManager, $entityManager->getClassMetaData(TournamentUser::class));
    },
    TournamentInvitationRepository::class => function (ContainerInterface $container): TournamentInvitationRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new TournamentInvitationRepository(
            $entityManager,
            $entityManager->getClassMetaData(TournamentInvitation::class)
        );
    },
    UserRepository::class => function (ContainerInterface $container): UserRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new UserRepository($entityManager, $entityManager->getClassMetaData(User::class));
    },
    SponsorRepository::class => function (ContainerInterface $container): SponsorRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new SponsorRepository($entityManager, $entityManager->getClassMetaData(Sponsor::class));
    },
    CompetitorRepository::class => function (ContainerInterface $container): CompetitorRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new CompetitorRepository($entityManager, $entityManager->getClassMetaData(Competitor::class));
    },
    LockerRoomRepository::class => function (ContainerInterface $container): LockerRoomRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new LockerRoomRepository($entityManager, $entityManager->getClassMetaData(LockerRoom::class));
    },

    SportRepository::class => function (ContainerInterface $container): SportRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new SportRepository($entityManager, $entityManager->getClassMetaData(Sport::class));
    },
    SeasonRepository::class => function (ContainerInterface $container): SeasonRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new SeasonRepository($entityManager, $entityManager->getClassMetaData(Season::class));
    },
    LeagueRepository::class => function (ContainerInterface $container): LeagueRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new LeagueRepository($entityManager, $entityManager->getClassMetaData(League::class));
    },
    CompetitionRepository::class => function (ContainerInterface $container): CompetitionRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new CompetitionRepository($entityManager, $entityManager->getClassMetaData(Competition::class));
    },

    StructureRepository::class => function (ContainerInterface $container): StructureRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new StructureRepository($entityManager);
    },
    PlanningRepository::class => function (ContainerInterface $container): PlanningRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new PlanningRepository($entityManager, $entityManager->getClassMetaData(Planning::class));
    },
    PlanningInputRepository::class => function (ContainerInterface $container): PlanningInputRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new PlanningInputRepository(
            $entityManager,
            $entityManager->getClassMetaData(PlanningInput::class)
        );
    },
    GameRepository::class => function (ContainerInterface $container): GameRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new GameRepository($entityManager, $entityManager->getClassMetaData(Game::class));
    },
    GameScoreRepository::class => function (ContainerInterface $container): GameScoreRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new GameScoreRepository($entityManager, $entityManager->getClassMetaData(GameScore::class));
    },
    FieldRepository::class => function (ContainerInterface $container): FieldRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new FieldRepository($entityManager, $entityManager->getClassMetaData(Field::class));
    },
    RefereeRepository::class => function (ContainerInterface $container): RefereeRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new RefereeRepository($entityManager, $entityManager->getClassMetaData(Referee::class));
    },
    SportConfigRepository::class => function (ContainerInterface $container): SportConfigRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new SportConfigRepository($entityManager, $entityManager->getClassMetaData(SportConfig::class));
    },
    SportScoreConfigRepository::class => function (ContainerInterface $container): SportScoreConfigRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new SportScoreConfigRepository(
            $entityManager,
            $entityManager->getClassMetaData(SportScoreConfig::class)
        );
    },
    RoundNumberRepository::class => function (ContainerInterface $container): RoundNumberRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new RoundNumberRepository($entityManager, $entityManager->getClassMetaData(RoundNumber::class));
    },
    PouleRepository::class => function (ContainerInterface $container): PouleRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new PouleRepository($entityManager, $entityManager->getClassMetaData(Poule::class));
    },
    PlaceRepository::class => function (ContainerInterface $container): PlaceRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new PlaceRepository($entityManager, $entityManager->getClassMetaData(Place::class));
    },
    PlanningConfigRepository::class => function (ContainerInterface $container): PlanningConfigRepository {
        $entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        return new PlanningConfigRepository($entityManager, $entityManager->getClassMetaData(PlanningConfig::class));
    },
];
