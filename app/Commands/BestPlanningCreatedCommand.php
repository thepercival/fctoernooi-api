<?php

declare(strict_types=1);

namespace App\Commands;

use App\Command;
use App\GuzzleClient;
use App\Mailer;
use App\QueueService\BestPlanningCreated as BestPlanningCreatedQueueService;
use Doctrine\ORM\EntityManagerInterface;
use FCToernooi\CacheService;
use FCToernooi\Planning\PlanningWriter;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Interop\Queue\Consumer;
use Interop\Queue\Message;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializerInterface;
use Memcached;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Sports\Competition;
use Sports\Competition\Repository as CompetitionRepository;
use Sports\Round\Number as RoundNumber;
use Sports\Round\Number\InputConfigurationCreator;
use Sports\Round\Number\Repository as RoundNumberRepository;
use Sports\Structure\Repository as StructureRepository;
use SportsPlanning\Input\Configuration as InputConfiguration;
use SportsPlanning\Referee\Info as PlanningRefereeInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BestPlanningCreatedCommand extends Command
{
    private string $customName = 'planning-available-listener';
    private bool $processSingleMessage = false;

    protected CompetitionRepository $competitionRepos;
    protected  StructureRepository $structureRepos;
    protected  RoundNumberRepository $roundNumberRepos;
    protected  TournamentRepository $tournamentRepos;
    protected EntityManagerInterface $entityManager;
    private Memcached $memcached;

    public function __construct(ContainerInterface $container, private SerializerInterface $serializer)
    {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);

        parent::__construct($config);

        /** @var Mailer|null $mailer */
        $mailer = $container->get(Mailer::class);
        $this->mailer = $mailer;

        /** @var CompetitionRepository $competitionRepos */
        $competitionRepos = $container->get(CompetitionRepository::class);
        $this->competitionRepos = $competitionRepos;

        /** @var StructureRepository $structureRepos */
        $structureRepos = $container->get(StructureRepository::class);
        $this->structureRepos = $structureRepos;

        /** @var RoundNumberRepository $roundNumberRepos */
        $roundNumberRepos = $container->get(RoundNumberRepository::class);
        $this->roundNumberRepos = $roundNumberRepos;


        /** @var TournamentRepository $tournamentRepos */
        $tournamentRepos = $container->get(TournamentRepository::class);
        $this->tournamentRepos = $tournamentRepos;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        $this->memcached = $container->get(Memcached::class);
    }

    protected function configure(): void
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:' . $this->customName)
            // the short description shown while running "php bin/console list"
            ->setDescription('Creates the planning for the related roundNumbers with no planning')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Creates the planning for the roundNumbers');

        $this->addOption('processSingleMessage', null, InputOption::VALUE_NONE, 'stop after 1 message received');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $loggerName = 'command-' . $this->customName;
            $logger = $this->initLogger(
                $this->getLogLevelFromInput($input),
                $this->getMailLogFromInput($input),
                $this->getPathOrStdOutFromInput($input, $loggerName),
                $loggerName,
            );
            $logger->info('starting command app:' . $this->customName);

            $processSingleMessage = $input->getOption('processSingleMessage');
            $this->processSingleMessage = is_bool($processSingleMessage) ? $processSingleMessage : false;

            $timeoutInSeconds = 295;
            $bestPlanningCreatedQueueService = new BestPlanningCreatedQueueService(
                $this->config->getArray('queue'));

            $bestPlanningCreatedQueueService->receive($this->getReceiver(), $timeoutInSeconds);
        } catch (\Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }

    protected function getReceiver(): callable
    {
        return function (Message $message, Consumer $consumer): bool {
            // process message
            try {
                $planningInputConfiguration = $this->getDeserializedInputConfiguration($message->getBody());

                $logger = $this->getLogger();
                $logMessage = 'processing inputConfiguration : "' . $planningInputConfiguration->getName() . '"';
                $logger->info($logMessage);

                $cacheService = new CacheService($this->memcached, $this->config->getString('namespace'));
                $planningWriter = new PlanningWriter($cacheService, $this->entityManager, $logger);

                $url = $this->config->getString('scheduler.url');
                $apikey = $this->config->getString('scheduler.apikey');
                $planningClient = new GuzzleClient($url, $apikey, $cacheService, $this->serializer, $logger);

                $nrOfCompetitionsAssigned = 0;
                foreach( $cacheService->getCompetitionIdsWithoutPlanning() as $competitionId ) {

                    $competition = $this->competitionRepos->find($competitionId);
                    if( $competition === null ) {
                        $cacheService->removeCompetitionIdWithoutPlanning($competitionId);
                        $logger->warning('   competition with id "' .$competitionId. '" not found');
                        continue;
                    }
                    $tournament = $this->tournamentRepos->findOneBy(['competition' => $competition]);
                    if( $tournament === null ) {
                        $logger->warning('   tournament with competitionId "' .$competitionId. '" not found');
                        $cacheService->removeCompetitionIdWithoutPlanning($competitionId);
                        continue;
                    }

                    $this->refreshCompetition($competition);

                    $structure = $this->structureRepos->getStructure($competition);

//                    $refereeInfo = new PlanningRefereeInfo( $roundNumber->getRefereeInfo());
//                    $inputConfiguration = (new InputConfigurationCreator())->create($roundNumber, $refereeInfo);
//                    if( $inputConfiguration->getName() !== $planningInputConfiguration->getName() ) {
//                        $this->getLogger()->info('inputConfigs do not match => continue');
//                        continue;
//                    }

                    try {
                        $roundNumbersWithPlanning = $planningClient->getRoundNumbersWithPlanning(
                            $competition, $structure->getRoundNumbers(), false );

                        $planningWriter->write($tournament, $roundNumbersWithPlanning);

                        $nrOfCompetitionsAssigned++;

                        $logger->info('  for competitionId "' . $competitionId . '" planning assigned');
                    } catch (\Exception $e ) {
                        $logger->info('  for competitionId "' . $competitionId . '" no valid planning yet ('.$e->getMessage().')');
                    }
                }

//                $tournamentId = (string)$createMessage->getTournament()->getId();
//                $logMessage = 'creating pdf for tournamentId "' . $tournamentId . '"';
//                $logMessage .= ' with subject "' . $createMessage->getSubject()->name . '"';
//                $this->getLogger()->info($logMessage
//);
                $logger->info('  nrOfCompetitionsAssigned : ' . $nrOfCompetitionsAssigned);
                $consumer->acknowledge($message);

                if( $this->processSingleMessage ) {
                    return false;
                }

            } catch (\Exception $exception) {
                if ($this->logger !== null) {
                    $this->logger->error($exception->getMessage());
                }
                $consumer->reject($message);
            }
            return true;
        };
    }

    protected function getDeserializedInputConfiguration(string $jsonInputConfiguration): InputConfiguration
    {
        /** @var InputConfiguration $inputConfiguration */
        $inputConfiguration = $this->serializer->deserialize(
            $jsonInputConfiguration,
            InputConfiguration::class,
            'json'
        );

        return $inputConfiguration;
    }

    protected function getFirstRoundNumber(Competition $competition): RoundNumber
    {
        $roundNumberAsValue = 1;
        $structure = $this->structureRepos->getStructure($competition);
        $roundNumber = $structure->getRoundNumber($roundNumberAsValue);
        if ($roundNumber === null) {
            throw new \Exception(
                "roundnumber " . $roundNumberAsValue . " not found for competitionid " . ((string)$competition->getId()),
                E_ERROR
            );
        }
        return $roundNumber;
    }

    protected function refreshCompetition(Competition $competition): void
    {
        $this->entityManager->refresh($competition);
        foreach ($competition->getSports() as $sport) {
            $this->entityManager->refresh($sport);
        }
        $roundNumber = $this->getFirstRoundNumber($competition);
        $this->refreshRoundNumber($roundNumber);
    }

    protected function refreshRoundNumber(RoundNumber $roundNumber): void
    {
        $this->entityManager->refresh($roundNumber);

        $this->entityManager->refresh($roundNumber);
        foreach ($roundNumber->getRounds() as $round) {
            $this->entityManager->refresh($round);
            foreach ($round->getPoules() as $poule) {
                $this->entityManager->refresh($poule);
//                foreach ($poule->getAgainstGames() as $game) {
//                    $this->entityManager->refresh($game);
//                }
            }
        }
        $planningConfig = $roundNumber->getPlanningConfig();
        if ($planningConfig !== null) {
            $this->entityManager->refresh($planningConfig);
        }
        foreach ($roundNumber->getValidGameAmountConfigs() as $gameAmountConfig) {
            $this->entityManager->refresh($gameAmountConfig);
        }

        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $this->refreshRoundNumber($nextRoundNumber);
        }
    }

}
