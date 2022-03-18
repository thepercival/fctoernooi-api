<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use FCToernooi\Competitor;
use FCToernooi\Competitor\Repository as CompetitorRepository;
use FCToernooi\CreditAction;
use FCToernooi\CreditAction\Repository as CreditActionRepository;
use FCToernooi\LockerRoom;
use FCToernooi\LockerRoom\Repository as LockerRoomRepository;
use FCToernooi\Recess;
use FCToernooi\Recess\Repository as RecessRepository;
use FCToernooi\Sponsor;
use FCToernooi\Sponsor\Repository as SponsorRepository;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Invitation as TournamentInvitation;
use FCToernooi\Tournament\Invitation\Repository as TournamentInvitationRepository;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\TournamentUser;
use FCToernooi\TournamentUser\Repository as TournamentUserRepository;
use FCToernooi\User;
use FCToernooi\User\Repository as UserRepository;
use Psr\Container\ContainerInterface;
use Sports\Competition;
use Sports\Competition\Field;
use Sports\Competition\Field\Repository as FieldRepository;
use Sports\Competition\Referee;
use Sports\Competition\Referee\Repository as RefereeRepository;
use Sports\Competition\Repository as CompetitionRepository;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Competition\Sport\Repository as CompetitionSportRepository;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Against\Repository as AgainstGameRepository;
use Sports\Game\Together as TogetherGame;
use Sports\Game\Together\Repository as TogetherGameRepository;
use Sports\League;
use Sports\League\Repository as LeagueRepository;
use Sports\Place;
use Sports\Place\Repository as PlaceRepository;
use Sports\Planning\Config as PlanningConfig;
use Sports\Planning\Config\Repository as PlanningConfigRepository;
use Sports\Planning\GameAmountConfig;
use Sports\Planning\GameAmountConfig\Repository as GameAmountConfigRepository;
use Sports\Poule;
use Sports\Poule\Horizontal\Creator as HorizontalPouleCreator;
use Sports\Poule\Repository as PouleRepository;
use Sports\Qualify\AgainstConfig as AgainstQualifyConfig;
use Sports\Qualify\AgainstConfig\Repository as AgainstQualifyConfigRepository;
use Sports\Qualify\Rule\Creator as QualifyRuleCreator;
use Sports\Round\Number as RoundNumber;
use Sports\Round\Number\Repository as RoundNumberRepository;
use Sports\Score\Against as AgainstScore;
use Sports\Score\Against\Repository as AgainstScoreRepository;
use Sports\Score\Config as ScoreConfig;
use Sports\Score\Config\Repository as ScoreConfigRepository;
use Sports\Score\Together as TogetherScore;
use Sports\Score\Together\Repository as TogetherScoreRepository;
use Sports\Season;
use Sports\Season\Repository as SeasonRepository;
use Sports\Sport;
use Sports\Sport\Repository as SportRepository;
use Sports\Structure\Repository as StructureRepository;
use SportsPlanning\Input as PlanningInput;
use SportsPlanning\Input\Repository as PlanningInputRepository;
use SportsPlanning\Planning;
use SportsPlanning\Planning\Repository as PlanningRepository;
use SportsPlanning\Schedule;
use SportsPlanning\Schedule\Repository as ScheduleRepository;

return [
    TournamentRepository::class => function (ContainerInterface $container): TournamentRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(Tournament::class);
        return new TournamentRepository($entityManager, $metaData);
    },
    TournamentUserRepository::class => function (ContainerInterface $container): TournamentUserRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(TournamentUser::class);
        return new TournamentUserRepository($entityManager, $metaData);
    },
    TournamentInvitationRepository::class => function (ContainerInterface $container): TournamentInvitationRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(TournamentInvitation::class);
        return new TournamentInvitationRepository($entityManager, $metaData);
    },
    UserRepository::class => function (ContainerInterface $container): UserRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(User::class);
        return new UserRepository($entityManager, $metaData);
    },
    CreditActionRepository::class => function (ContainerInterface $container): CreditActionRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(CreditAction::class);
        return new CreditActionRepository($entityManager, $metaData);
    },
    SponsorRepository::class => function (ContainerInterface $container): SponsorRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(Sponsor::class);
        return new SponsorRepository($entityManager, $metaData);
    },
    RecessRepository::class => function (ContainerInterface $container): RecessRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(Recess::class);
        return new RecessRepository($entityManager, $metaData);
    },
    CompetitorRepository::class => function (ContainerInterface $container): CompetitorRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(Competitor::class);
        return new CompetitorRepository($entityManager, $metaData);
    },
    LockerRoomRepository::class => function (ContainerInterface $container): LockerRoomRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(LockerRoom::class);
        return new LockerRoomRepository($entityManager, $metaData);
    },

    SportRepository::class => function (ContainerInterface $container): SportRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(Sport::class);
        return new SportRepository($entityManager, $metaData);
    },
    SeasonRepository::class => function (ContainerInterface $container): SeasonRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(Season::class);
        return new SeasonRepository($entityManager, $metaData);
    },
    LeagueRepository::class => function (ContainerInterface $container): LeagueRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(League::class);
        return new LeagueRepository($entityManager, $metaData);
    },
    CompetitionRepository::class => function (ContainerInterface $container): CompetitionRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(Competition::class);
        return new CompetitionRepository($entityManager, $metaData);
    },
    StructureRepository::class => function (ContainerInterface $container): StructureRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        return new StructureRepository(
            $entityManager,
            new HorizontalPouleCreator(),
            new QualifyRuleCreator()
        );
    },
    PlanningRepository::class => function (ContainerInterface $container): PlanningRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(Planning::class);
        return new PlanningRepository($entityManager, $metaData);
    },
    PlanningInputRepository::class => function (ContainerInterface $container): PlanningInputRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(PlanningInput::class);
        return new PlanningInputRepository($entityManager, $metaData);
    },
    ScheduleRepository::class => function (ContainerInterface $container): ScheduleRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(Schedule::class);
        return new ScheduleRepository($entityManager, $metaData);
    },
    AgainstGameRepository::class => function (ContainerInterface $container): AgainstGameRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(AgainstGame::class);
        return new AgainstGameRepository($entityManager, $metaData);
    },
    TogetherGameRepository::class => function (ContainerInterface $container): TogetherGameRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(TogetherGame::class);
        return new TogetherGameRepository($entityManager, $metaData);
    },
    AgainstScoreRepository::class => function (ContainerInterface $container): AgainstScoreRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(AgainstScore::class);
        return new AgainstScoreRepository($entityManager, $metaData);
    },
    TogetherScoreRepository::class => function (ContainerInterface $container): TogetherScoreRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(TogetherScore::class);
        return new TogetherScoreRepository($entityManager, $metaData);
    },
    FieldRepository::class => function (ContainerInterface $container): FieldRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(Field::class);
        return new FieldRepository($entityManager, $metaData);
    },
    RefereeRepository::class => function (ContainerInterface $container): RefereeRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(Referee::class);
        return new RefereeRepository($entityManager, $metaData);
    },
    CompetitionSportRepository::class => function (ContainerInterface $container): CompetitionSportRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(CompetitionSport::class);
        return new CompetitionSportRepository($entityManager, $metaData);
    },
    ScoreConfigRepository::class => function (ContainerInterface $container): ScoreConfigRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(ScoreConfig::class);
        return new ScoreConfigRepository($entityManager, $metaData);
    },
    AgainstQualifyConfigRepository::class => function (ContainerInterface $container): AgainstQualifyConfigRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(AgainstQualifyConfig::class);
        return new AgainstQualifyConfigRepository($entityManager, $metaData);
    },
    RoundNumberRepository::class => function (ContainerInterface $container): RoundNumberRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(RoundNumber::class);
        return new RoundNumberRepository($entityManager, $metaData);
    },
    PouleRepository::class => function (ContainerInterface $container): PouleRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(Poule::class);
        return new PouleRepository($entityManager, $metaData);
    },
    PlaceRepository::class => function (ContainerInterface $container): PlaceRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(Place::class);
        return new PlaceRepository($entityManager, $metaData);
    },
    PlanningConfigRepository::class => function (ContainerInterface $container): PlanningConfigRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(PlanningConfig::class);
        return new PlanningConfigRepository($entityManager, $metaData);
    },
    GameAmountConfigRepository::class => function (ContainerInterface $container): GameAmountConfigRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(GameAmountConfig::class);
        return new GameAmountConfigRepository($entityManager, $metaData);
    },
];
