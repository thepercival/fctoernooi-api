<?php

namespace App\Commands\Planning;

use App\Mailer;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use App\Command;
use Selective\Config\Configuration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Voetbal\Planning\Repository as PlanningRepository;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;
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

;

class Create extends Command
{
    /**
     * @var PlanningInputRepository
     */
    protected $planningInputRepos;
    /**
     * @var PlanningRepository
     */
    protected $planningRepos;
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
        // $settings = $container->get('settings');
        $this->planningInputRepos = $container->get(PlanningInputRepository::class);
        $this->planningRepos = $container->get(PlanningRepository::class);
        $this->structureRepos = $container->get(StructureRepository::class);
        $this->tournamentRepos = $container->get(TournamentRepository::class);
        parent::__construct($container->get(Configuration::class), 'cron-planning-create');
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
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            if ($this->planningInputRepos->isProcessing()) {
                $this->logger->info("still processing..");
                return 0;
            }
            $planningInput = $this->planningInputRepos->getFirstUnsuccessful();
            // $planningInput = $this->planningInputRepos->find( 55492 );
            if ($planningInput === null) {
                $this->logger->info("nothing to process");
                return 0;
            }
            $planningSeeker = new PlanningSeeker($this->logger, $this->planningInputRepos, $this->planningRepos);
            $planningSeeker->process($planningInput);
            $nrUpdated = $this->addPlannigsToRoundNumbers($planningInput);
            $this->logger->info($nrUpdated . " structure(s)-planning updated");
        } catch (\Exception $e) {
            if ($this->config->getString('environment') === 'production') {
                $this->mailer->sendToAdmin("error creating planning", $e->getMessage());
                $this->logger->error($e->getMessage());
            } else {
                echo $e->getMessage() . PHP_EOL;
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
