<?php

namespace App\Commands\Planning;

use App\QueueService;
use \Exception;
use Psr\Container\ContainerInterface;
use App\Command;
use SportsHelpers\PouleStructure;
use SportsPlanning\Planning;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Selective\Config\Configuration;
use FCToernooi\Tournament\Repository as TournamentRepository;
use SportsPlanning\Output\Batch as BatchOutput;
use SportsPlanning\Planning\Output as PlanningOutput;
use SportsPlanning\Input as PlanningInput;
use SportsPlanning\Input\Repository as PlanningInputRepository;
use SportsPlanning\Planning\Repository as PlanningRepository;
use SportsPlanning\Planning\Service as PlanningService;
use SportsPlanning\Planning\Validator as PlanningValidator;

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

        $this->addOption('pouleStructure', null, InputOption::VALUE_OPTIONAL, '6,6');
//        $this->addOption('structure', null, InputOption::VALUE_OPTIONAL, '3|2|2|');
//        $this->addOption('sportConfig', null, InputOption::VALUE_OPTIONAL, '2|2');
//        $this->addOption('nrOfReferees', null, InputOption::VALUE_OPTIONAL, '0');
//        $this->addOption('nrOfHeadtohead', null, InputOption::VALUE_OPTIONAL, '1');
//        $this->addOption('teamup', null, InputOption::VALUE_OPTIONAL, 'false');
        $this->addOption('selfReferee', null, InputOption::VALUE_OPTIONAL, '0,1 or 2');
        $this->addOption('exitAtFirstInvalid', null, InputOption::VALUE_OPTIONAL, 'false|true');
        $this->addOption('maxNrOfInputs', null, InputOption::VALUE_OPTIONAL, '100');
        $this->addOption('resetInvalid', null, InputOption::VALUE_NONE);
        $this->addOption('validateInvalid', null, InputOption::VALUE_NONE);

        $this->addArgument('inputId', InputArgument::OPTIONAL, 'input-id');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, 'cron-planning-validator');

        $resetPlanningInputWhenInvalid = $input->getOption('resetInvalid');

        $showNrOfPlaces = [];
        if (strlen($input->getArgument('inputId')) > 0) {
            $planningInput = $this->planningInputRepos->find((int)$input->getArgument('inputId'));
            $this->validatePlanningInput($planningInput, $resetPlanningInputWhenInvalid);
            $this->logger->info('planningInput ' . $input->getArgument('inputId') . ' gevalideerd');
            return 0;
        }
        $this->exitAtFirstInvalid = filter_var($input->getOption("exitAtFirstInvalid"), FILTER_VALIDATE_BOOLEAN);
        $maxNrOfInputs = 100;
        if (strlen($input->getOption('maxNrOfInputs')) > 0) {
            $maxNrOfInputs = (int)$input->getOption('maxNrOfInputs');
        }
        $validateInvalid = $input->getOption("validateInvalid");
        $queueService = new QueueService($this->config->getArray('queue'));
        $pouleStructure = $this->getPouleStructure($input);
        $selfReferee = $this->getSelfReferee($input);
        $this->logger->info('aan het valideren..');
        $planningInputs = $this->planningInputRepos->findNotValidated($validateInvalid, $maxNrOfInputs, $pouleStructure, $selfReferee);
        foreach ($planningInputs as $planningInput) {
            (new PlanningOutput($this->logger))->outputInput( $planningInput );
            try {
                $this->validatePlanningInput($planningInput, $resetPlanningInputWhenInvalid, $showNrOfPlaces);
            } catch (Exception $e) {
                $this->logger->error( $e->getMessage() );

                if ($this->exitAtFirstInvalid) {
                    return 0;
                }
                if( $resetPlanningInputWhenInvalid ) {
                    $this->planningInputRepos->reset($planningInput);
                    $queueService->sendCreatePlannings($planningInput);
                }
            }
            $this->planningInputRepos->getEM()->clear();
        }
        $this->logger->info('alle planningen gevalideerd');
        return 0;
    }

    protected function validatePlanningInput(PlanningInput $planningInputParam, bool $resetPlanningInputWhenInvalid, array &$showNrOfPlaces = null)
    {
        $planningInput = $this->planningInputRepos->getFromInput($planningInputParam);

        if ($planningInput === null) {
            return;
        }
        if ($showNrOfPlaces !== null && array_key_exists($planningInput->getNrOfPlaces(), $showNrOfPlaces) === false) {
            $this->logger->info("TRYING NROFPLACES: " . $planningInput->getNrOfPlaces());
            $showNrOfPlaces[$planningInput->getNrOfPlaces()] = true;
        }
        $succeededPlannings = $planningInput->getPlannings( Planning::STATE_SUCCEEDED );
        if ($succeededPlannings->count() === 0) {
            throw new \Exception( "input (inputid " . $planningInput->getId() . ") has no bestplanning", E_ERROR );
        }
        $validator = new PlanningValidator();
        foreach( $succeededPlannings as $succeededPlanning ) {
            if ($succeededPlanning->getValidity() === PlanningValidator::VALID) {
                continue;
            }
            $validity = $validator->validate($succeededPlanning);
            $this->setValidity($succeededPlanning, $validity);
            $validations = $validator->getValidityDescriptions($validity);
            if (count($validations) === 0) {
                continue;
            }
            // output
            $planningOutput = new PlanningOutput($this->logger);
            $planningOutput->outputWithGames($succeededPlanning, true);
            $planningOutput->outputWithTotals($succeededPlanning, true);

            throw new \Exception(reset($validations), E_ERROR);
        }
    }

    protected function setValidity( Planning $planning, int $validity ) {
        $planning->setValidity($validity);
        $this->planningRepos->save($planning);
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
        if( $pouleStructure === null ) {
            return null;
        }
        return new PouleStructure($pouleStructure);
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
