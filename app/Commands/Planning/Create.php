<?php

namespace App\Commands\Planning;

use App\Mailer;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Voetbal\Game as GameBase;
use Voetbal\Output\Planning as PlanningOutput;
use Voetbal\Output\Planning\Batch as BatchOutput;
use Voetbal\Planning as PlanningBase;
use Voetbal\Planning\Output;
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
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, 'cron-planning-create');

        try {
            if ($this->planningInputRepos->isProcessing(PlanningInput::STATE_TRYING_PLANNINGS)) {
                $this->checkMoreThanTwoCompetitionsWithoutPlanning();
                $this->logger->info("still processing..");
                return 0;
            }
            $planningInput = $this->planningInputRepos->getFirstUnsuccessful();
            // $planningInput = $this->planningInputRepos->find( 25163 );
            if ($planningInput === null) {
                $this->logger->info("nothing to process");
                return 0;
            }

            $em = $this->planningInputRepos->getEM();
            $conn = $em->getConnection();
            $conn->beginTransaction();
            try {
                $planningSeeker = new PlanningSeeker($this->logger, $this->planningInputRepos, $this->planningRepos);
                $planningSeeker->process($planningInput);

                if ($planningInput->getSelfReferee()) {
                    $this->updateSelfReferee($planningInput);
                }

//            $planningService = new PlanningService();
//            $planning = $planningService->getBestPlanning($planningInput);
//            $sortedGames = $planning->getGames(GameBase::ORDER_BY_BATCH);
//            $planningOutput = new \Voetbal\Planning\Output($this->logger);
//            $planningOutput->consoleGames($sortedGames);


                $nrUpdated = $this->addPlannigsToRoundNumbers($planningInput);
                $this->logger->info($nrUpdated . " structure(s)-planning updated");

                $em->flush();
                $conn->commit();
            } catch (\Exception $e) {
                $conn->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return 0;
    }

    protected function addPlannigsToRoundNumbers(PlanningInput $planningInput): int
    {
        $nrUpdated = 0;
        $structures = $this->structureRepos->getStructures(["hasPlanning" => false]);
        $structureValidator = new StructureValidator();
        foreach ($structures as $structure) {
            $competition = $structure->getFirstRoundNumber()->getCompetition();
            try {
                $structureValidator->checkValidity($competition, $structure);
                if ($this->addPlanningToRoundNumber($structure->getFirstRoundNumber(), $planningInput) === true) {
                    $nrUpdated++;
                };
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }
        return $nrUpdated;
    }

    protected function addPlanningToRoundNumber(RoundNumber $roundNumber, PlanningInput $planningInput): bool
    {
        if ($roundNumber->getHasPlanning()) {
            return $this->addPlanningToRoundNumber($roundNumber->getNext(), $planningInput);
        }
        $inputService = new PlanningInputService();
        $planningService = new PlanningService();
        if (!$inputService->areEqual($inputService->get($roundNumber), $planningInput)) {
            return false;
        }
        $planning = $planningService->getBestPlanning($planningInput);
        if ($planning === null) {
            return false;
        }

        $tournament = $this->tournamentRepos->findOneBy(["competition" => $roundNumber->getCompetition()]);
        $planningCreator = new PlanningCreator($this->planningInputRepos, $this->planningRepos);
        $planningCreator->create($roundNumber, $tournament->getBreak());
        return true;
    }

    protected function checkMoreThanTwoCompetitionsWithoutPlanning()
    {
        if ($this->competitionRepos->findNrWithoutPlanning() >= 2) {
            $this->mailer->sendToAdmin('more than two competitions without planning', 'check it out');
        }
    }
}
