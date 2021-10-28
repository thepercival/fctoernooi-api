<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

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
use SportsPlanning\Schedule\Repository as ScheduleRepository;
use SportsPlanning\Schedule;
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
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<Tournament> $metaData */
        $metaData = $entityManager->getClassMetadata(Tournament::class);
        return new TournamentRepository($entityManager, $metaData);
    },
    TournamentUserRepository::class => function (ContainerInterface $container): TournamentUserRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<TournamentUser> $metaData */
        $metaData = $entityManager->getClassMetadata(TournamentUser::class);
        return new TournamentUserRepository($entityManager, $metaData);
    },
    TournamentInvitationRepository::class => function (ContainerInterface $container): TournamentInvitationRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<TournamentInvitation> $metaData */
        $metaData = $entityManager->getClassMetadata(TournamentInvitation::class);
        return new TournamentInvitationRepository($entityManager, $metaData);
    },
    UserRepository::class => function (ContainerInterface $container): UserRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<User> $metaData */
        $metaData = $entityManager->getClassMetadata(User::class);
        return new UserRepository($entityManager, $metaData);
    },
    SponsorRepository::class => function (ContainerInterface $container): SponsorRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<Sponsor> $metaData */
        $metaData = $entityManager->getClassMetadata(Sponsor::class);
        return new SponsorRepository($entityManager, $metaData);
    },
    CompetitorRepository::class => function (ContainerInterface $container): CompetitorRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<Competitor> $metaData */
        $metaData = $entityManager->getClassMetadata(Competitor::class);
        return new CompetitorRepository($entityManager, $metaData);
    },
    LockerRoomRepository::class => function (ContainerInterface $container): LockerRoomRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<LockerRoom> $metaData */
        $metaData = $entityManager->getClassMetadata(LockerRoom::class);
        return new LockerRoomRepository($entityManager, $metaData);
    },

    SportRepository::class => function (ContainerInterface $container): SportRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<Sport> $metaData */
        $metaData = $entityManager->getClassMetadata(Sport::class);
        return new SportRepository($entityManager, $metaData);
    },
    SeasonRepository::class => function (ContainerInterface $container): SeasonRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<Season> $metaData */
        $metaData = $entityManager->getClassMetadata(Season::class);
        return new SeasonRepository($entityManager, $metaData);
    },
    LeagueRepository::class => function (ContainerInterface $container): LeagueRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<League> $metaData */
        $metaData = $entityManager->getClassMetadata(League::class);
        return new LeagueRepository($entityManager, $metaData);
    },
    CompetitionRepository::class => function (ContainerInterface $container): CompetitionRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<Competition> $metaData */
        $metaData = $entityManager->getClassMetadata(Competition::class);
        return new CompetitionRepository($entityManager, $metaData);
    },
    StructureRepository::class => function (ContainerInterface $container): StructureRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        return new StructureRepository(
            $entityManager,
            new HorizontalPouleCreator(),
            new QualifyRuleCreator()
        );
    },
    PlanningRepository::class => function (ContainerInterface $container): PlanningRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<Planning> $metaData */
        $metaData = $entityManager->getClassMetadata(Planning::class);
        return new PlanningRepository($entityManager, $metaData);
    },
    PlanningInputRepository::class => function (ContainerInterface $container): PlanningInputRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<PlanningInput> $metaData */
        $metaData = $entityManager->getClassMetadata(PlanningInput::class);
        return new PlanningInputRepository($entityManager, $metaData);
    },
    ScheduleRepository::class => function (ContainerInterface $container): ScheduleRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<Schedule> $metaData */
        $metaData = $entityManager->getClassMetadata(Schedule::class);
        return new ScheduleRepository($entityManager, $metaData);
    },
    GameRepository::class => function (ContainerInterface $container): GameRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<Game> $metaData */
        $metaData = $entityManager->getClassMetadata(Game::class);
        return new GameRepository($entityManager, $metaData);
    },
    AgainstGameRepository::class => function (ContainerInterface $container): AgainstGameRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<AgainstGame> $metaData */
        $metaData = $entityManager->getClassMetadata(AgainstGame::class);
        return new AgainstGameRepository($entityManager, $metaData);
    },
    TogetherGameRepository::class => function (ContainerInterface $container): TogetherGameRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<TogetherGame> $metaData */
        $metaData = $entityManager->getClassMetadata(TogetherGame::class);
        return new TogetherGameRepository($entityManager, $metaData);
    },
    AgainstScoreRepository::class => function (ContainerInterface $container): AgainstScoreRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<AgainstScore> $metaData */
        $metaData = $entityManager->getClassMetadata(AgainstScore::class);
        return new AgainstScoreRepository($entityManager, $metaData);
    },
    TogetherScoreRepository::class => function (ContainerInterface $container): TogetherScoreRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<TogetherScore> $metaData */
        $metaData = $entityManager->getClassMetadata(TogetherScore::class);
        return new TogetherScoreRepository($entityManager, $metaData);
    },
    FieldRepository::class => function (ContainerInterface $container): FieldRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<Field> $metaData */
        $metaData = $entityManager->getClassMetadata(Field::class);
        return new FieldRepository($entityManager, $metaData);
    },
    RefereeRepository::class => function (ContainerInterface $container): RefereeRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<Referee> $metaData */
        $metaData = $entityManager->getClassMetadata(Referee::class);
        return new RefereeRepository($entityManager, $metaData);
    },
    CompetitionSportRepository::class => function (ContainerInterface $container): CompetitionSportRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<CompetitionSport> $metaData */
        $metaData = $entityManager->getClassMetadata(CompetitionSport::class);
        return new CompetitionSportRepository($entityManager, $metaData);
    },
    ScoreConfigRepository::class => function (ContainerInterface $container): ScoreConfigRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<ScoreConfig> $metaData */
        $metaData = $entityManager->getClassMetadata(ScoreConfig::class);
        return new ScoreConfigRepository($entityManager, $metaData);
    },
    AgainstQualifyConfigRepository::class => function (ContainerInterface $container): AgainstQualifyConfigRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<AgainstQualifyConfig> $metaData */
        $metaData = $entityManager->getClassMetadata(AgainstQualifyConfig::class);
        return new AgainstQualifyConfigRepository($entityManager, $metaData);
    },
    RoundNumberRepository::class => function (ContainerInterface $container): RoundNumberRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<RoundNumber> $metaData */
        $metaData = $entityManager->getClassMetadata(RoundNumber::class);
        return new RoundNumberRepository($entityManager, $metaData);
    },
    PouleRepository::class => function (ContainerInterface $container): PouleRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<Poule> $metaData */
        $metaData = $entityManager->getClassMetadata(Poule::class);
        return new PouleRepository($entityManager, $metaData);
    },
    PlaceRepository::class => function (ContainerInterface $container): PlaceRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<Place> $metaData */
        $metaData = $entityManager->getClassMetadata(Place::class);
        return new PlaceRepository($entityManager, $metaData);
    },
    PlanningConfigRepository::class => function (ContainerInterface $container): PlanningConfigRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<PlanningConfig> $metaData */
        $metaData = $entityManager->getClassMetadata(PlanningConfig::class);
        return new PlanningConfigRepository($entityManager, $metaData);
    },
    GameAmountConfigRepository::class => function (ContainerInterface $container): GameAmountConfigRepository {
        /** @var EntityManager $entityManager */
        $entityManager = $container->get(EntityManager::class);
        /** @psalm-var ClassMetadata<GameAmountConfig> $metaData */
        $metaData = $entityManager->getClassMetadata(GameAmountConfig::class);
        return new GameAmountConfigRepository($entityManager, $metaData);
    },
];
