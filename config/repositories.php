<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManager;
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

use Sports\Poule\Horizontal\Creator as HorizontalPouleCreator;
use Sports\Sport\Repository as SportRepository;
use Sports\Sport;
use Sports\Season\Repository as SeasonRepository;
use Sports\Season;
use Sports\League\Repository as LeagueRepository;
use Sports\League;
use Sports\Competition\Repository as CompetitionRepository;
use Sports\Competition;

use Sports\Structure\Repository as StructureRepository;
use SportsPlanning\Planning\Repository as PlanningRepository;
use SportsPlanning\Planning;
use SportsPlanning\Input\Repository as PlanningInputRepository;
use SportsPlanning\Input as PlanningInput;
use Sports\Game\Repository as GameRepository;
use Sports\Game as Game;
use Sports\Game\Against\Repository as AgainstGameRepository;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Together\Repository as TogetherGameRepository;
use Sports\Game\Together as TogetherGame;
use Sports\Score\Against\Repository as AgainstScoreRepository;
use Sports\Score\Against as AgainstScore;
use Sports\Score\Together\Repository as TogetherScoreRepository;
use Sports\Score\Together as TogetherScore;
use Sports\Competition\Field\Repository as FieldRepository;
use Sports\Competition\Field;
use Sports\Competition\Referee\Repository as RefereeRepository;
use Sports\Competition\Referee;
use Sports\Competition\Sport\Repository as CompetitionSportRepository;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Score\Config\Repository as ScoreConfigRepository;
use Sports\Score\Config as ScoreConfig;
use Sports\Qualify\AgainstConfig\Repository as AgainstQualifyConfigRepository;
use Sports\Qualify\AgainstConfig as AgainstQualifyConfig;
use Sports\Place\Repository as PlaceRepository;
use Sports\Place;
use Sports\Poule\Repository as PouleRepository;
use Sports\Poule;
use Sports\Round\Number\Repository as RoundNumberRepository;
use Sports\Round\Number as RoundNumber;
use Sports\Planning\Config\Repository as PlanningConfigRepository;
use Sports\Planning\Config as PlanningConfig;
use Sports\Planning\GameAmountConfig\Repository as GameAmountConfigRepository;
use Sports\Planning\GameAmountConfig;
use Sports\Qualify\Rule\Creator as QualifyRuleCreator;

return [
    TournamentRepository::class => function (ContainerInterface $container): TournamentRepository {
        $entityManager = $container->get(EntityManager::class);
        return new TournamentRepository($entityManager, $entityManager->getClassMetaData(Tournament::class));
    },
    TournamentUserRepository::class => function (ContainerInterface $container): TournamentUserRepository {
        $entityManager = $container->get(EntityManager::class);
        return new TournamentUserRepository($entityManager, $entityManager->getClassMetaData(TournamentUser::class));
    },
    TournamentInvitationRepository::class => function (ContainerInterface $container): TournamentInvitationRepository {
        $entityManager = $container->get(EntityManager::class);
        return new TournamentInvitationRepository(
            $entityManager,
            $entityManager->getClassMetaData(TournamentInvitation::class)
        );
    },
    UserRepository::class => function (ContainerInterface $container): UserRepository {
        $entityManager = $container->get(EntityManager::class);
        return new UserRepository($entityManager, $entityManager->getClassMetaData(User::class));
    },
    SponsorRepository::class => function (ContainerInterface $container): SponsorRepository {
        $entityManager = $container->get(EntityManager::class);
        return new SponsorRepository($entityManager, $entityManager->getClassMetaData(Sponsor::class));
    },
    CompetitorRepository::class => function (ContainerInterface $container): CompetitorRepository {
        $entityManager = $container->get(EntityManager::class);
        return new CompetitorRepository($entityManager, $entityManager->getClassMetaData(Competitor::class));
    },
    LockerRoomRepository::class => function (ContainerInterface $container): LockerRoomRepository {
        $entityManager = $container->get(EntityManager::class);
        return new LockerRoomRepository($entityManager, $entityManager->getClassMetadata(LockerRoom::class));
    },

    SportRepository::class => function (ContainerInterface $container): SportRepository {
        $entityManager = $container->get(EntityManager::class);
        return new SportRepository($entityManager, $entityManager->getClassMetaData(Sport::class));
    },
    SeasonRepository::class => function (ContainerInterface $container): SeasonRepository {
        $entityManager = $container->get(EntityManager::class);
        return new SeasonRepository($entityManager, $entityManager->getClassMetaData(Season::class));
    },
    LeagueRepository::class => function (ContainerInterface $container): LeagueRepository {
        $entityManager = $container->get(EntityManager::class);
        return new LeagueRepository($entityManager, $entityManager->getClassMetaData(League::class));
    },
    CompetitionRepository::class => function (ContainerInterface $container): CompetitionRepository {
        $entityManager = $container->get(EntityManager::class);
        return new CompetitionRepository($entityManager, $entityManager->getClassMetaData(Competition::class));
    },

    StructureRepository::class => function (ContainerInterface $container): StructureRepository {
        $entityManager = $container->get(EntityManager::class);
        return new StructureRepository(
            $entityManager,
            new HorizontalPouleCreator(),
            new QualifyRuleCreator());
    },
    PlanningRepository::class => function (ContainerInterface $container): PlanningRepository {
        $entityManager = $container->get(EntityManager::class);
        return new PlanningRepository($entityManager, $entityManager->getClassMetaData(Planning::class));
    },
    PlanningInputRepository::class => function (ContainerInterface $container): PlanningInputRepository {
        $entityManager = $container->get(EntityManager::class);
        return new PlanningInputRepository(
            $entityManager,
            $entityManager->getClassMetaData(PlanningInput::class)
        );
    },
    GameRepository::class => function (ContainerInterface $container): GameRepository {
        $entityManager = $container->get(EntityManager::class);
        return new GameRepository($entityManager, $entityManager->getClassMetaData(Game::class));
    },
    AgainstGameRepository::class => function (ContainerInterface $container): AgainstGameRepository {
        $entityManager = $container->get(EntityManager::class);
        return new AgainstGameRepository($entityManager, $entityManager->getClassMetaData(AgainstGame::class));
    },
    TogetherGameRepository::class => function (ContainerInterface $container): TogetherGameRepository {
        $entityManager = $container->get(EntityManager::class);
        return new TogetherGameRepository($entityManager, $entityManager->getClassMetaData(TogetherGame::class));
    },
    AgainstScoreRepository::class => function (ContainerInterface $container): AgainstScoreRepository {
        $entityManager = $container->get(EntityManager::class);
        return new AgainstScoreRepository($entityManager, $entityManager->getClassMetaData(AgainstScore::class));
    },
    TogetherScoreRepository::class => function (ContainerInterface $container): TogetherScoreRepository {
        $entityManager = $container->get(EntityManager::class);
        return new TogetherScoreRepository($entityManager, $entityManager->getClassMetaData(TogetherScore::class));
    },
    FieldRepository::class => function (ContainerInterface $container): FieldRepository {
        $entityManager = $container->get(EntityManager::class);
        return new FieldRepository($entityManager, $entityManager->getClassMetaData(Field::class));
    },
    RefereeRepository::class => function (ContainerInterface $container): RefereeRepository {
        $entityManager = $container->get(EntityManager::class);
        return new RefereeRepository($entityManager, $entityManager->getClassMetaData(Referee::class));
    },
    CompetitionSportRepository::class => function (ContainerInterface $container): CompetitionSportRepository {
        $entityManager = $container->get(EntityManager::class);
        return new CompetitionSportRepository($entityManager, $entityManager->getClassMetaData(CompetitionSport::class));
    },
    ScoreConfigRepository::class => function (ContainerInterface $container): ScoreConfigRepository {
        $entityManager = $container->get(EntityManager::class);
        return new ScoreConfigRepository(
            $entityManager,
            $entityManager->getClassMetaData(ScoreConfig::class)
        );
    },
    AgainstQualifyConfigRepository::class => function (ContainerInterface $container): AgainstQualifyConfigRepository {
        $entityManager = $container->get(EntityManager::class);
        return new AgainstQualifyConfigRepository(
            $entityManager,
            $entityManager->getClassMetaData(AgainstQualifyConfig::class)
        );
    },
    RoundNumberRepository::class => function (ContainerInterface $container): RoundNumberRepository {
        $entityManager = $container->get(EntityManager::class);
        return new RoundNumberRepository($entityManager, $entityManager->getClassMetaData(RoundNumber::class));
    },
    PouleRepository::class => function (ContainerInterface $container): PouleRepository {
        $entityManager = $container->get(EntityManager::class);
        return new PouleRepository($entityManager, $entityManager->getClassMetaData(Poule::class));
    },
    PlaceRepository::class => function (ContainerInterface $container): PlaceRepository {
        $entityManager = $container->get(EntityManager::class);
        return new PlaceRepository($entityManager, $entityManager->getClassMetaData(Place::class));
    },
    PlanningConfigRepository::class => function (ContainerInterface $container): PlanningConfigRepository {
        $entityManager = $container->get(EntityManager::class);
        return new PlanningConfigRepository($entityManager, $entityManager->getClassMetaData(PlanningConfig::class));
    },
    GameAmountConfigRepository::class => function (ContainerInterface $container): GameAmountConfigRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        return new GameAmountConfigRepository(
            $entityManager,
            $entityManager->getClassMetaData(GameAmountConfig::class)
        );
    },
];
