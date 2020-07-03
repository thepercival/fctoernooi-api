<?php

namespace App\Commands\Planning;

use App\Mailer;
use App\QueueService;
use Interop\Queue\Consumer;
use Interop\Queue\Message;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Voetbal\Competition;
use Voetbal\Output\Planning as PlanningOutput;
use Voetbal\Output\Planning\Batch as BatchOutput;
use Voetbal\Planning as PlanningBase;
use Voetbal\Structure\Repository as StructureRepository;

use Voetbal\Planning\Input as PlanningInput;
use Voetbal\Planning\Input\Service as PlanningInputService;
use Voetbal\Planning\Seeker as PlanningSeeker;
use Voetbal\Planning\Service as PlanningService;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Voetbal\Round\Number\PlanningCreator;
use Voetbal\Competition\Repository as CompetitionRepository;
use Voetbal\Round\Number as RoundNumber;
use Voetbal\Structure\Validator as StructureValidator;
use App\Commands\Planning as PlanningCommand;

class Create extends PlanningCommand
{
    /**
     * @var StructureRepository
     */
    protected $structureRepos;
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;
    /**
     * @var CompetitionRepository
     */
    protected $competitionRepos;
    /**
     * @var Mailer
     */
    protected $mailer;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->mailer = $container->get(Mailer::class);
        $this->structureRepos = $container->get(StructureRepository::class);
        $this->tournamentRepos = $container->get(TournamentRepository::class);
        $this->competitionRepos = $container->get(CompetitionRepository::class);
    }

    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:create-planning')
            // the short description shown while running "php bin/console list"
            ->setDescription('Creates the plannings from the inputs')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Creates the plannings from the inputs');
        parent::configure();

        $this->addArgument('inputId', InputArgument::OPTIONAL, 'input-id');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, 'cron-planning-create');

        try {
            $receiver = function (Message $message, Consumer $consumer): void {
                // process message

                try {
                    $content = json_decode($message->getBody());
                    $competition = $this->competitionRepos->find((int)$content->competitionId);
                    if ($competition === null) {
                        throw new \Exception("competition is not found, tournament maybe deleted already", E_ERROR);
                    }
                    $this->processPlanning($competition, (int)$content->roundNumber);
                    $consumer->acknowledge($message);
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());
                    $consumer->reject($message);
                }
            };

            $queue = new QueueService();
            $timeoutInSeconds = 295;
            $queue->receive($receiver, $timeoutInSeconds);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return 0;
    }

    protected function processPlanning(Competition $competition, int $roundNumberAsValue)
    {
        $this->logger->info("competitionid: " . $competition->getId() . " => " . $roundNumberAsValue . "(rn)");

        $roundNumber = $this->getFreshRoundNumber($competition, $roundNumberAsValue);
        $planningInputService = new PlanningInputService();
        $nrOfReferees = $competition->getReferees()->count();
        $planningInput = $planningInputService->get($roundNumber, $nrOfReferees);
        $this->planningInputRepos->getEM()->flush();
//        $em = $this->planningInputRepos->getEM();
//        $conn = $em->getConnection();
//        $conn->beginTransaction();

        $planningSeeker = new PlanningSeeker($this->logger, $this->planningInputRepos, $this->planningRepos);
        $planningSeeker->process($planningInput);
        if ($planningInput->getSelfReferee()) {
            $this->updateSelfReferee($planningInput);
        }

        $planningService = new PlanningBase\Service();
        $bestPlanning = $planningService->getBestPlanning($planningInput);
        if ($bestPlanning === null) {
            throw new \Exception(
                "best planning not found for roundnumber " . $roundNumberAsValue . " and competitionid " . $competition->getId(
                ), E_ERROR
            );
        }
        $freshRoundNumber = $this->getFreshRoundNumber($roundNumber->getCompetition(), $roundNumber->getNumber());
        $this->connectPlanningInputWithRoundNumber($freshRoundNumber, $bestPlanning);

//            $planningOutput = new PlanningOutput($this->logger);
//            $planningOutput->outputWithGames($bestPlanning, false);
//            $planningOutput->outputWithTotals($bestPlanning, false);

//                $nrUpdated = $this->addPlannigsToRoundNumbers($planningInput);
//                $this->logger->info($nrUpdated . " structure(s)-planning updated");

//            $em->flush();
//            $conn->commit();
//        } catch (\Exception $e) {
//            $conn->rollBack();
//            throw $e;
//        }
        $this->planningInputRepos->getEM()->flush();
    }

//    protected function connectPlanningInputWithRoundNumber(PlanningInput $planningInput, RoundNumber $roundNumber)
//    {
//        $roundNumber
//
//        try {
//            $structureValidator->checkValidity($competition, $structure);
//            if ($this->addPlanningToRoundNumber($structure->getFirstRoundNumber(), $planningInput) === true) {
//                $nrUpdated++;
//            };
//        } catch (\Exception $e) {
//            $this->logger->error($e->getMessage());
//        }
//    }

    protected function connectPlanningInputWithRoundNumber(RoundNumber $roundNumber, PlanningBase $bestPlanning): bool
    {
        $planningInput = $bestPlanning->getInput();
        if ($roundNumber->getHasPlanning()) {
            if ($roundNumber->hasNext()) {
                return $this->connectPlanningInputWithRoundNumber($roundNumber->getNext(), $bestPlanning);
            }
            return true;
        }
        $inputService = new PlanningInputService();
        if (!$inputService->areEqual(
            $inputService->get($roundNumber, $roundNumber->getCompetition()->getReferees()->count()),
            $planningInput
        )) {
            return false;
        }

        $tournament = $this->tournamentRepos->findOneBy(["competition" => $roundNumber->getCompetition()]);
        $planningCreator = new PlanningCreator($this->planningInputRepos, $this->planningRepos);
        $planningCreator->addFrom($roundNumber, $tournament->getBreak());
        return true;
    }

    protected function getFreshRoundNumber(Competition $competition, int $roundNumberAsValue): RoundNumber
    {
        $this->competitionRepos->getEM()->clear();
        $structure = $this->structureRepos->getStructure($competition);
        if ($structure === null) {
            throw new \Exception("structure not found for competitionid " . $competition->getId(), E_ERROR);
        }
        $roundNumber = $structure->getRoundNumber($roundNumberAsValue);
        if ($roundNumber === null) {
            throw new \Exception(
                "roundnumber " . $roundNumberAsValue . " not found for competitionid " . $competition->getId(), E_ERROR
            );
        }
        return $roundNumber;
    }
}
