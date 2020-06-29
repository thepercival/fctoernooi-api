<?php

namespace App\Commands\Planning;

use \Exception;
use App\Mailer;
use FCToernooi\Tournament;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use App\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Selective\Config\Configuration;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Voetbal\Output\Planning\Batch as BatchOutput;
use Voetbal\Output\Planning as PlanningOutput;
use Voetbal\Planning;
use Voetbal\Planning\Input;
use Voetbal\Planning\Input\Iterator as PlanningInputIterator;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Range as VoetbalRange;
use Voetbal\Structure;
use Voetbal\Structure\Options as StructureOptions;
use Voetbal\Planning\Input\Repository as PlanningInputRepos;
use Voetbal\Planning\Validator as PlanningValidator;

class Validator extends Command
{
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;
    /**
     * @var PlanningInputRepos
     */
    protected $planningInputRepos;
    /**
     * @var PlanningValidator
     */
    protected $planningValidator;

    public function __construct(ContainerInterface $container)
    {
        $this->tournamentRepos = $container->get(TournamentRepository::class);
        $this->planningInputRepos = $container->get(PlanningInputRepository::class);
        $this->planningValidator = new PlanningValidator();
        parent::__construct($container->get(Configuration::class));
    }

    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:validate-planning')
            // the short description shown while running "php bin/console list"
            ->setDescription('validates the created plaining')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('validates the plannings');
        parent::configure();

        $this->addOption('structure', null, InputOption::VALUE_OPTIONAL, '3|2|2|');
        $this->addOption('sportConfig', null, InputOption::VALUE_OPTIONAL, '2|2');
        $this->addOption('nrOfReferees', null, InputOption::VALUE_OPTIONAL, '0');
        $this->addOption('nrOfHeadtohead', null, InputOption::VALUE_OPTIONAL, '1');
        $this->addOption('teamup', null, InputOption::VALUE_OPTIONAL, 'false');
        $this->addOption('selfReferee', null, InputOption::VALUE_OPTIONAL, 'false');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, 'cron-planning-validator');

        $planningService = new PlanningService();

        list($sportConfigRange, $fieldsRange) = $this->getSportConfigsRanges($input);

        $planningInputIterator = new PlanningInputIterator(
            $this->getStructureOptions($input),
            $sportConfigRange,
            $fieldsRange,
            $this->getRefereesRange($input),
            $this->getNrOfHeadtoheadRange($input)
        );

        $planningOutput = new PlanningOutput($this->logger);
        $batchOutput = new BatchOutput($this->logger);

        $this->logger->info('aan het valideren..');
        while ($planningInputIt = $planningInputIterator->increment()) {
            // $this->logger->info( $this->inputToString( $planningInput ) );

            $planningInput = $this->planningInputRepos->getFromInput($planningInputIt);

            // $this->assertNotEquals( $planningInput, null );

            if ($planningInput === null) {
                continue;
            }

            $bestPlanning = $planningService->getBestPlanning($planningInput);
            if ($bestPlanning === null) {
                continue;
            }

            $validator = new PlanningValidator();

            try {
                $planningOutput->outputWithGames($bestPlanning, true);
                $validator->validate($bestPlanning);
            } catch (Exception $e) {
                // $this->consolePlanning($e->getMessage(), $bestPlanning);
                $this->logger->error($e->getMessage());
                $planningOutput->output($bestPlanning, true);
                $batchOutput->output($bestPlanning->getFirstBatch(), 'best planning');
                break;
            }
        }
        $this->logger->info('alle planningen gevalideerd');
        return 0;
    }

    protected function getStructureOptions(InputInterface $input): StructureOptions
    {
        $tournamentStructureOptions = new Tournament\StructureOptions();
        $pouleRange = $tournamentStructureOptions->getPouleRange();
        $placeRange = $tournamentStructureOptions->getPlaceRange();
        if (strlen($input->getOption("structure")) > 0) {
            $poules = explode('|', $input->getOption('structure'));
            $nrOfPoules = count($poules);
            $nrOfPlaces = 0;
            foreach ($poules as $nrOfPlacesIt) {
                $nrOfPlaces += $nrOfPlacesIt;
            }
            $pouleRange = new VoetbalRange($nrOfPoules, $nrOfPoules);
            $placeRange = new VoetbalRange($nrOfPlaces, $nrOfPlaces);
        }
        return new StructureOptions(
            $pouleRange,
            $placeRange,
            $tournamentStructureOptions->getPlacesPerPouleRange()
        );
    }

    /**
     * @param InputInterface $input
     * @return array|VoetbalRange[]
     */
    protected function getSportConfigsRanges(InputInterface $input): array
    {
        $nrOfSportConfigsStart = 1;
        $nrOfSportConfigsEnd = 1;
        $nrOfFieldsStart = 1;
        $nrOfFieldsEnd = 10;
        if (strlen($input->getOption("sportConfig")) > 0) {
            $sportConfigs = explode('|', $input->getOption('sportConfig'));
            $nrOfSportConfigs = count($sportConfigs);
            $nrOfSportConfigsStart = $nrOfSportConfigs;
            $nrOfSportConfigsEnd = $nrOfSportConfigs;
            $nrOfFields = 0;
            foreach ($sportConfigs as $nrOfFieldsIt) {
                $nrOfFields += $nrOfFieldsIt;
            }
            $nrOfFieldsStart = $nrOfFields;
            $nrOfFieldsEnd = $nrOfFields;
        }
        return array(
            new VoetbalRange($nrOfSportConfigsStart, $nrOfSportConfigsEnd),
            new VoetbalRange($nrOfFieldsStart, $nrOfFieldsEnd)
        );
    }

    /**
     * @param InputInterface $input
     * @return VoetbalRange
     */
    protected function getRefereesRange(InputInterface $input): VoetbalRange
    {
        if (strlen($input->getOption("nrOfReferees")) > 0) {
            $nrOfReferees = filter_var($input->getOption('nrOfReferees'), FILTER_VALIDATE_INT);
            return new VoetbalRange($nrOfReferees, $nrOfReferees);
        }
        return new VoetbalRange(0, 10);
    }

    /**
     * @param InputInterface $input
     * @return VoetbalRange
     */
    protected function getNrOfHeadtoheadRange(InputInterface $input): VoetbalRange
    {
        if (strlen($input->getOption("nrOfHeadtohead")) > 0) {
            $nrOfHeadtohead = filter_var($input->getOption('nrOfHeadtohead'), FILTER_VALIDATE_INT);
            return new VoetbalRange($nrOfHeadtohead, $nrOfHeadtohead);
        }
        return new VoetbalRange(1, 2);
    }
}
