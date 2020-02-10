<?php

namespace App\Commands\Planning;

use App\Mailer;
use Complex\Exception;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use App\Command;
use Selective\Config\Configuration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Voetbal\Planning;
use Voetbal\Planning\Repository as PlanningRepository;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;
use Voetbal\Planning\Resource\RefereePlaceService;
use Voetbal\Structure\Repository as StructureRepository;

use Voetbal\Planning\Input as PlanningInput;
use Voetbal\Planning\Input\Service as PlanningInputService;
use Voetbal\Planning\Seeker as PlanningSeeker;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Planning\ConvertService as PlanningConvertService;
use Voetbal\Planning\ScheduleService as ScheduleService;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Voetbal\Round\Number\PlanningCreator;
use Voetbal\Round\Number as RoundNumber;
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


    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->structureRepos = $container->get(StructureRepository::class);
        $this->tournamentRepos = $container->get(TournamentRepository::class);

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
        $this->initMailer($this->logger);

        try {
            if ($this->planningInputRepos->isProcessing(PlanningInput::STATE_TRYING_PLANNINGS)) {
                $this->logger->info("still processing..");
                return 0;
            }
            $planningInput = $this->planningInputRepos->getFirstUnsuccessful();
            // $planningInput = $this->planningInputRepos->find( 1102 );
            if ($planningInput === null) {
                $this->logger->info("nothing to process");
                return 0;
            }
            $planningSeeker = new PlanningSeeker($this->logger, $this->planningInputRepos, $this->planningRepos);
            $planningSeeker->process($planningInput);

            if ($planningInput->getSelfReferee()) {
                $this->updateSelfReferee($planningInput);
            }

            $nrUpdated = $this->addPlannigsToRoundNumbers($planningInput);
            $this->logger->info($nrUpdated . " structure(s)-planning updated");
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            if ($this->config->getString('environment') === 'production') {
                $this->mailer->sendToAdmin("error creating planning", $e->getMessage());
            }
        }
        return 0;
    }

    protected function addPlannigsToRoundNumbers(PlanningInput $planningInput): int
    {
        $nrUpdated = 0;
        $structures = $this->structureRepos->getStructures(["hasPlanning" => false]);
        foreach ($structures as $structure) {
            if ($this->addPlanningToRoundNumber($structure->getFirstRoundNumber(), $planningInput) === true) {
                $nrUpdated++;
            };
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
}
