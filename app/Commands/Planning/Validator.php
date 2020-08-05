<?php

namespace App\Commands\Planning;

use App\QueueService;
use \Exception;
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
use Voetbal\Planning\Input as PlanningInput;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;
use Voetbal\Planning\Repository as PlanningRepository;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Planning\Validator as PlanningValidator;

class Validator extends Command
{
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;
    /**
     * @var PlanningInputRepository
     */
    protected $planningInputRepos;
    /**
     * @var PlanningRepository
     */
    protected $planningRepos;
    /**
     * @var PlanningValidator
     */
    protected $planningValidator;
    /**
     * @var bool
     */
    protected $exitAtFirstInvalid;

    public function __construct(ContainerInterface $container)
    {
        $this->tournamentRepos = $container->get(TournamentRepository::class);
        $this->planningInputRepos = $container->get(PlanningInputRepository::class);
        $this->planningRepos = $container->get(PlanningRepository::class);
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

        $this->addOption('structureConfig', null, InputOption::VALUE_OPTIONAL, '6,6');
//        $this->addOption('structure', null, InputOption::VALUE_OPTIONAL, '3|2|2|');
//        $this->addOption('sportConfig', null, InputOption::VALUE_OPTIONAL, '2|2');
//        $this->addOption('nrOfReferees', null, InputOption::VALUE_OPTIONAL, '0');
//        $this->addOption('nrOfHeadtohead', null, InputOption::VALUE_OPTIONAL, '1');
//        $this->addOption('teamup', null, InputOption::VALUE_OPTIONAL, 'false');
        $this->addOption('selfReferee', null, InputOption::VALUE_OPTIONAL, '0,1 or 2');
        $this->addOption('exitAtFirstInvalid', null, InputOption::VALUE_OPTIONAL, 'false|true');
        $this->addOption('maxNrOfInputs', null, InputOption::VALUE_OPTIONAL, '100');

        $this->addArgument('inputId', InputArgument::OPTIONAL, 'input-id');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, 'cron-planning-validator');

        $showNrOfPlaces = [];
        if (strlen($input->getArgument('inputId')) > 0) {
            $planningInput = $this->planningInputRepos->find((int)$input->getArgument('inputId'));
            $this->validatePlanningInput($planningInput);
            $this->logger->info('planningInput ' . $input->getArgument('inputId') . ' gevalideerd');
            return 0;
        }
        $this->exitAtFirstInvalid = filter_var($input->getOption("exitAtFirstInvalid"), FILTER_VALIDATE_BOOLEAN);
        $maxNrOfInputs = 100;
        if (strlen($input->getOption('maxNrOfInputs')) > 0) {
            $maxNrOfInputs = (int)$input->getOption('maxNrOfInputs');
        }
        $structureConfig = $this->getStructureConfig($input);
        $selfReferee = $this->getSelfReferee($input);
        $this->logger->info('aan het valideren..');
        $planningInputs = $this->planningInputRepos->findNotValidated($maxNrOfInputs, $structureConfig, $selfReferee);
        foreach ($planningInputs as $planningInput) {
            // $this->logger->info( $this->inputToString( $planningInput ) );
            try {
                $this->validatePlanningInput($planningInput, $showNrOfPlaces);
            } catch (Exception $e) {
                if ($this->exitAtFirstInvalid) {
                    return 0;
                }
            }
        }
        $this->logger->info('alle planningen gevalideerd');
        return 0;
    }

    protected function validatePlanningInput(PlanningInput $planningInputParam, array &$showNrOfPlaces = null)
    {
        $planningOutput = new PlanningOutput($this->logger);
        $planningInput = $this->planningInputRepos->getFromInput($planningInputParam);

        if ($planningInput === null) {
            return;
        }
        if ($showNrOfPlaces !== null && array_key_exists($planningInput->getNrOfPlaces(), $showNrOfPlaces) === false) {
            $this->logger->info("TRYING NROFPLACES: " . $planningInput->getNrOfPlaces());
            $showNrOfPlaces[$planningInput->getNrOfPlaces()] = true;
        }
        $queueService = new QueueService($this->config->getArray('queue'));
        $planningService = new PlanningService();
        $bestPlanning = $planningService->getBestPlanning($planningInput);
        if ($bestPlanning === null) {
            $queueService->sendCreatePlannings($planningInput);
            return;
        }

        if ($bestPlanning->getValidity() === PlanningValidator::VALID) {
            // $planningOutput->outputWithGames($bestPlanning, true);
            return;
        }

        $validator = new PlanningValidator();
        $oldValidity = $bestPlanning->getValidity();
        $validity = $validator->validate($bestPlanning);
        $validations = $validator->getValidityDescriptions($validity);
        if (count($validations) > 0) {
            foreach ($validations as $validation) {
                $this->logger->error($validation . "(inputid " . $planningInput->getId() . ")");
            }
            if ($this->exitAtFirstInvalid) {
                $planningOutput->outputWithGames($bestPlanning, true);
                $planningOutput->outputWithTotals($bestPlanning, true);
                throw new Exception("exits at first error", E_ERROR);
            } else {
                $planningOutput->outputWithGames($bestPlanning, true);
                $planningOutput->outputWithTotals($bestPlanning, true);
            }
        }
        $bestPlanning->setValidity($validity);
        $this->planningRepos->save($bestPlanning);

        if ($oldValidity === PlanningValidator::NOT_VALIDATED
            && ($validity & PlanningValidator::ALL_INVALID) > 0) {
            $this->planningInputRepos->reset($planningInput);
            $queueService->sendCreatePlannings($planningInput);
        }
    }

    /**
     * @param InputInterface $input
     * @return array|int[]|null
     */
    protected function getStructureConfig(InputInterface $input): ?array
    {
        $structureConfig = null;
        if (strlen($input->getOption('structureConfig')) > 0) {
            $structureConfigParam = explode(",", $input->getOption('structureConfig'));
            if ($structureConfigParam != false) {
                $structureConfig = [];
                foreach ($structureConfigParam as $nrOfPlaces) {
                    $structureConfig[] = (int)$nrOfPlaces;
                }
            }
        }
        return $structureConfig;
    }

    /**
     * @param InputInterface $input
     * @return int|null
     */
    protected function getSelfReferee(InputInterface $input): ?int
    {
        $selfReferee = null;
        if (strlen($input->getOption('selfReferee')) > 0 && ctype_digit($input->getOption('selfReferee')) ) {
            $selfReferee = (int) $input->getOption('selfReferee');
            if ($selfReferee !== PlanningInput::SELFREFEREE_OTHERPOULES && $selfReferee !== PlanningInput::SELFREFEREE_SAMEPOULE ) {
                $selfReferee = PlanningInput::SELFREFEREE_DISABLED;
            }
        }
        return $selfReferee;
    }
}
