<?php

namespace App\Commands\Planning;

use App\Mailer;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use App\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Voetbal\Planning\Repository as PlanningRepository;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;
use Voetbal\Round\Number\Repository as RoundNumberRepository;

use Voetbal\Planning\Input as PlanningInput;
use Voetbal\Planning\Input\Service as PlanningInputService;
use Voetbal\Planning\Seeker as PlanningSeeker;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Planning\ConvertService as PlanningConvertService;
use Voetbal\Planning\ScheduleService as ScheduleService;
use FCToernooi\Tournament\Repository as TournamentRepository;

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
     * @var RoundNumberRepository
     */
    protected $roundNumberRepos;
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;

    public function __construct(ContainerInterface $container)
    {
        // $settings = $container->get('settings');
        $this->planningInputRepos = $container->get(PlanningInputRepository::class);
        $this->planningRepos = $container->get(PlanningRepository::class);
        $this->roundNumberRepos = $container->get(RoundNumberRepository::class);
        $this->tournamentRepos = $container->get(TournamentRepository::class);
        parent::__construct($container, 'cron-planning-create');
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
            $this->logger->info($nrUpdated . " roundnumber(s)-planning updated");
        } catch (\Exception $e) {
            if ($this->env === 'production') {
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
        $roundNumbers = $this->roundNumberRepos->findBy(["hasPlanning" => false]);
        $inputService = new PlanningInputService();
        $planningService = new PlanningService();
        foreach ($roundNumbers as $roundNumber) {
            if (!$inputService->areEqual($inputService->get($roundNumber), $planningInput)) {
                continue;
            }
            $planning = $planningService->getBestPlanning($planningInput);
            if ($planning === null) {
                continue;
            }
            $tournament = $this->tournamentRepos->findOneBy(["competition" => $roundNumber->getCompetition()]);
            $convertService = new PlanningConvertService(new ScheduleService($tournament->getBreak()));
            $convertService->createGames($roundNumber, $planning);
            $this->planningRepos->saveRoundNumber($roundNumber, true);
            $nrUpdated++;
        }
        return $nrUpdated;
    }
}
