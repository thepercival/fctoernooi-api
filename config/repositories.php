<?php

declare(strict_types=1);

use App\Repositories\CompetitorRepository;
use App\Repositories\LockerRoomRepository;
use App\Repositories\SponsorRepository;
use App\Repositories\Sports\AgainstGameRepository;
use App\Repositories\Sports\AgainstQualifyConfigRepository;
use App\Repositories\Sports\AgainstScoreRepository;
use App\Repositories\Sports\CompetitionSportRepository;
use App\Repositories\Sports\RoundNumberRepository;
use App\Repositories\Sports\SportRepository;
use App\Repositories\Sports\StructureRepository;
use App\Repositories\Sports\TogetherGameRepository;
use App\Repositories\Sports\TogetherScoreRepository;
use App\Repositories\TournamentInvitationRepository;
use App\Repositories\TournamentRegistrationRepository;
use App\Repositories\TournamentRegistrationSettingsRepository;
use App\Repositories\TournamentRepository;
use Doctrine\ORM\EntityManagerInterface;
use FCToernooi\Competitor;
use FCToernooi\LockerRoom;
use FCToernooi\Sponsor;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Registration as TournamentRegistration;
use FCToernooi\Tournament\Invitation as TournamentInvitation;
use FCToernooi\Tournament\RegistrationSettings as TournamentRegistrationSettings;
use Psr\Container\ContainerInterface;
use Sports\Competition\CompetitionSport;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Together as TogetherGame;
use Sports\Poule\Horizontal\Creator as HorizontalPouleCreator;
use Sports\Qualify\AgainstConfig as AgainstQualifyConfig;
use Sports\Qualify\Rule\Creator as QualifyRuleCreator;
use Sports\Round\Number as RoundNumber;
use Sports\Score\Against as AgainstScore;
use Sports\Score\Together as TogetherScore;
use Sports\Sport;

return [
    TournamentRepository::class => function (ContainerInterface $container): TournamentRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(Tournament::class);
        return new TournamentRepository($entityManager, $metaData);
    },
    SponsorRepository::class => function (ContainerInterface $container): SponsorRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(Sponsor::class);
        return new SponsorRepository($entityManager, $metaData);
    },
    TournamentRegistrationRepository::class => function (ContainerInterface $container): TournamentRegistrationRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(TournamentRegistration::class);
        return new TournamentRegistrationRepository($entityManager, $metaData);
    },
    TournamentRegistrationSettingsRepository::class => function (ContainerInterface $container): TournamentRegistrationSettingsRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(TournamentRegistrationSettings::class);
        return new TournamentRegistrationSettingsRepository($entityManager, $metaData);
    },
    TournamentInvitationRepository::class => function (ContainerInterface $container): TournamentInvitationRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(TournamentInvitation::class);
        return new TournamentInvitationRepository($entityManager, $metaData);
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
    StructureRepository::class => function (ContainerInterface $container): StructureRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        return new StructureRepository(
            $entityManager,
            new HorizontalPouleCreator(),
            new QualifyRuleCreator()
        );
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
    CompetitionSportRepository::class => function (ContainerInterface $container): CompetitionSportRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(CompetitionSport::class);
        return new CompetitionSportRepository($entityManager, $metaData);
    },
    SportRepository::class => function (ContainerInterface $container): SportRepository {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metaData = $entityManager->getClassMetadata(Sport::class);
        return new SportRepository($entityManager, $metaData);
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
];
