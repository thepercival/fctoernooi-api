<?php

namespace App\Commands\Planning;

use App\Mailer;
use FCToernooi\Tournament;
use FCToernooi\Tournament\StructureRanges as TournamentStructureRanges;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use SportsHelpers\Place\Range as PlaceRange;
use SportsHelpers\PouleStructure;
use SportsHelpers\Range;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use SportsPlanning\Input as PlanningInput;
use SportsPlanning\Planning as PlanningBase;
use SportsPlanning\Planning\Output as PlanningOutput;
use SportsPlanning\Batch\Output as BatchOutput;
use SportsPlanning\Input\GCDService as PlanningInputGCDService;
use SportsPlanning\Planning\Seeker as PlanningSeeker;
use App\Commands\Planning as PlanningCommand;
use SportsHelpers\PouleStructure\Balanced\Iterator as PouleStructureIterator;

class RetryTimeout extends PlanningCommand
{
    /**
     * @var Mailer
     */
    protected $mailer;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->mailer = $container->get(Mailer::class);
    }

    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:retry-timeout-planning')
            // the short description shown while running "php bin/console list"
            ->setDescription('Retries the timeout-plannings')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Retries the timeout-plannings');
        parent::configure();

        $this->addArgument('inputId', InputArgument::OPTIONAL, 'input-id');
        $this->addOption('placesRange', null, InputOption::VALUE_OPTIONAL, '2-6');
        $this->addOption('maxTimeoutSeconds', null, InputOption::VALUE_OPTIONAL, '5');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, 'cron-retry-timeout-planning');
        $planningSeeker = new PlanningSeeker($this->logger, $this->planningInputRepos, $this->planningRepos);
        $planningSeeker->enableTimedout( $this->getMaxTimeoutSeconds( $input ) );

        try {
            if ( !$this->setStatusToStartProcessingTimedout() ) {
                $this->logger->info("still processing..");
                return 0;
            }

            $planningInput = $this->getPlanningInputFromInput( $input );
            $oldBestPlanning = $planningInput->getBestPlanning();
            $planningSeeker->process( $planningInput );
            if( $oldBestPlanning !== $planningInput->getBestPlanning() ) {
                $this->sendMailWithSuccessfullTimedoutPlanning($planningInput);
            }

            $this->updatePolynomials( $planningInput );

            $this->setStatusToFinishedProcessingTimedout();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return 0;
    }

    protected function getPlanningInputFromInput( InputInterface $input ): PlanningInput {
        if (strlen($input->getArgument('inputId')) > 0) {
            $planningInput = $this->planningInputRepos->find((int)$input->getArgument('inputId'));
            if ($planningInput === null) {
                throw new \Exception(
                    "no timedout-planninginput found for inputId " . $input->getArgument('inputId'),
                    E_ERROR
                );
            }
            return $planningInput;
        }
        $tournamentStructureRanges = new TournamentStructureRanges();
        $placesRange = $this->getPlaceRange($input, $tournamentStructureRanges);
        $maxTimeOutSeconds = $this->getMaxTimeoutSeconds( $input );
        $planningInput = $this->getTimedoutInput( true, $maxTimeOutSeconds, $placesRange );
        if ( $planningInput === null) {
            $planningInput = $this->getTimedoutInput( false, $maxTimeOutSeconds, $placesRange );
        }

        if ( $planningInput === null) {
            $suffix = "maxTimeoutSeconds: ".$maxTimeOutSeconds;
            $suffix .= ", placesRange: " . $placesRange->min . "-" . $placesRange->max;
            throw new \Exception("no timedout-planning found for " . $suffix, E_ERROR );
        }
        return $planningInput;
}

    protected function getTimedoutInput( bool $batchGames, int $maxTimeOutSeconds, PlaceRange $placesRange ): ?PlanningInput {
        $structureIterator = new PouleStructureIterator( $placesRange, new Range(1, 64) );
        while( $structureIterator->valid() ) {
            // echo PHP_EOL . $structureIterator->current()->toString();
            $planningInput = null;
            if( $batchGames ) {
                $planningInput = $this->planningInputRepos->findBatchGamestTimedout($maxTimeOutSeconds, $structureIterator->current() );
            } else {
                $planningInput = $this->planningInputRepos->findGamesInARowTimedout($maxTimeOutSeconds, $structureIterator->current() );
            }
            if( $planningInput !== null ) {
                return $planningInput;
            }
            $structureIterator->next();

        }
        return null;
    }

    protected function getMaxTimeoutSeconds( InputInterface $input ): int {
        if (strlen($input->getOption('maxTimeoutSeconds')) > 0) {
            return (int) $input->getOption('maxTimeoutSeconds');
        }
        return PlanningBase::DEFAULT_TIMEOUTSECONDS * pow( PlanningBase::TIMEOUT_MULTIPLIER, 2 );
    }

    protected function updatePolynomials(PlanningInput $planningInput) {
        $inputGCDService = new PlanningInputGCDService();

        for ($polynomial = 2; $polynomial <= 8; $polynomial++) {
            $polynomialInputTmp = $inputGCDService->getPolynomial($planningInput, $polynomial);
            $polynomialInput = $this->planningInputRepos->getFromInput($polynomialInputTmp);
            if ($polynomialInput === null) {
                continue;
            }

            $plannings = $polynomialInput->getPlannings();
            while ($plannings->count() > 0) {
                $removePlanning = $plannings->first();
                $plannings->removeElement($removePlanning);
                $this->planningRepos->remove($removePlanning);
            }
            $this->planningInputRepos->save($polynomialInput);
        }
    }

    protected function sendMailWithSuccessfullTimedoutPlanning(PlanningInput $planningInput)
    {
        $stream = fopen('php://memory', 'r+');
        $loggerOutput = new Logger('succesfull-retry-planning-output-logger');
        $loggerOutput->pushProcessor(new UidProcessor());
        $handler = new StreamHandler($stream, Logger::INFO);
        $loggerOutput->pushHandler($handler);

        $planningOutput = new PlanningOutput($loggerOutput);
        $planningOutput->outputInput($planningInput, null, "is succesfull");

        $batchOutput = new BatchOutput($loggerOutput);
        $batchOutput->output($planningInput->getBestPlanning()->createFirstBatch(), "succesful retry planning");

        rewind($stream);
        $this->mailer->sendToAdmin(
            'timeout-planning successful',
            stream_get_contents($stream/*$handler->getStream()*/),
            true
        );
    }

    /**
     * @param InputInterface $input
     * @return PouleStructure|null
     */
    protected function getPouleStructure(InputInterface $input): ?PouleStructure
    {
        $pouleStructure = null;
        if (strlen($input->getOption('pouleStructure')) > 0) {
            $pouleStructureParam = explode(",", $input->getOption('pouleStructure'));
            if ($pouleStructureParam != false) {
                $pouleStructure = [];
                foreach ($pouleStructureParam as $nrOfPlaces) {
                    $pouleStructure[] = (int)$nrOfPlaces;
                }
            }
        }
        return new PouleStructure($pouleStructure);
    }

    protected function setStatusToStartProcessingTimedout(): bool {

        $fileName = $this->getStatusFileName();
        if( file_exists ( $fileName ) ) {
            return false;
        }
        file_put_contents( $fileName, "processing");
        return true;
    }

    protected function setStatusToFinishedProcessingTimedout() {
        unlink( $this->getStatusFileName() );
    }

    protected function getStatusFileName(): string {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . "timedout-processing.txt";
    }

}
