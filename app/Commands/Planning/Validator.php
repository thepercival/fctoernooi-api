<?php
declare(strict_types=1);

namespace App\Commands\Planning;

use App\Mailer;
use App\QueueService;
use \Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use App\Command;
use SportsHelpers\PouleStructure;
use SportsPlanning\Planning;
use SportsHelpers\SelfReferee;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Selective\Config\Configuration;
use FCToernooi\Tournament\Repository as TournamentRepository;
use SportsPlanning\Planning\Output as PlanningOutput;
use SportsPlanning\Input as PlanningInput;
use SportsPlanning\Input\Repository as PlanningInputRepository;
use SportsPlanning\Planning\Repository as PlanningRepository;
use SportsPlanning\Planning\Validator as PlanningValidator;

class Validator extends Command
{
    protected TournamentRepository $tournamentRepos;
    protected PlanningInputRepository $planningInputRepos;
    protected PlanningRepository $planningRepos;
    protected PlanningValidator $planningValidator;
    protected bool $exitAtFirstInvalid = false;

    public function __construct(ContainerInterface $container)
    {
        $this->tournamentRepos = $container->get(TournamentRepository::class);
        $this->planningInputRepos = $container->get(PlanningInputRepository::class);
        $this->planningRepos = $container->get(PlanningRepository::class);
        $this->planningValidator = new PlanningValidator();
        $this->mailer = $container->get(Mailer::class);
        parent::__construct($container->get(Configuration::class));
    }

    protected function configure(): void
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
        $this->addOption('selfReferee', null, InputOption::VALUE_OPTIONAL, '0,1 or 2');
        $this->addOption('exitAtFirstInvalid', null, InputOption::VALUE_OPTIONAL, 'false|true');
        $this->addOption('maxNrOfInputs', null, InputOption::VALUE_OPTIONAL, '100');
        $this->addOption('resetInvalid', null, InputOption::VALUE_NONE);
        $this->addOption('validateInvalid', null, InputOption::VALUE_NONE);
        $this->addOption('sendEmailWhenInvalid', null, InputOption::VALUE_NONE);

        $this->addArgument('inputId', InputArgument::OPTIONAL, 'input-id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initLogger($input, 'command-planning-validate');

        $resetPlanningInputWhenInvalid = $input->getOption('resetInvalid');
        $resetPlanningInputWhenInvalid = is_bool($resetPlanningInputWhenInvalid) ? $resetPlanningInputWhenInvalid : false;

        $sendEmailWhenInvalid = $input->getOption('sendEmailWhenInvalid');
        $sendEmailWhenInvalid = is_bool($sendEmailWhenInvalid) ? $sendEmailWhenInvalid : false;

        $showNrOfPlaces = [];
        $invalidPlanningInputsMessages = [];

        $this->exitAtFirstInvalid = filter_var($input->getOption("exitAtFirstInvalid"), FILTER_VALIDATE_BOOLEAN);

        $queueService = new QueueService($this->config->getArray('queue'));

        $this->getLogger()->info('valideren gestart..');

        $planningInputs = $this->getPlanningInputsToValidate($input);
        foreach ($planningInputs as $planningInput) {
            (new PlanningOutput($this->getLogger()))->outputInput($planningInput, 'validating.. ');
            try {
                $this->validatePlanningInput($planningInput, $resetPlanningInputWhenInvalid, $showNrOfPlaces);
            } catch (Exception $exception) {
                $errorMsg = 'inputid: ' . (string)$planningInput->getId() . ' (' . $planningInput->getUniqueString() . ') => ' . $exception->getMessage();
                if ($this->logger !== null) {
                    $this->logger->error($errorMsg);
                }
                if ($this->exitAtFirstInvalid) {
                    return 0;
                }
                if ($resetPlanningInputWhenInvalid) {
                    $this->planningInputRepos->reset($planningInput);
                    $queueService->sendCreatePlannings($planningInput);
                }
                $invalidPlanningInputsMessages[] = $errorMsg;
            }
            // $this->planningInputRepos->getEM()->clear();
        }
        $this->getLogger()->info('alle planningen gevalideerd');

        if (count($invalidPlanningInputsMessages) > 0 && $sendEmailWhenInvalid) {
            $this->sendMailWithInvalid($invalidPlanningInputsMessages);
        }

        return 0;
    }

    /**
     * @return list<PlanningInput>
     */
    protected function getPlanningInputsToValidate(InputInterface $input): array
    {
        $inputId = $input->getArgument('inputId');
        if (is_string($inputId) && strlen($inputId) > 0) {
            $planningInput = $this->planningInputRepos->find((int)$inputId);
            if ($planningInput === null) {
                return [];
            }
            return [ $planningInput ];
        }

        $validateInvalid = $input->getOption("validateInvalid");
        $validateInvalid = is_bool($validateInvalid) ? $validateInvalid : false;

        $pouleStructure = $this->getPouleStructure($input);
        $selfReferee = $this->getSelfReferee($input);

        $maxNrOfInputs = 100;
        $maxNrOfInputsInput = $input->getOption('maxNrOfInputs');
        if (is_string($maxNrOfInputsInput) && strlen($maxNrOfInputsInput) > 0) {
            $maxNrOfInputs = (int)$maxNrOfInputsInput;
        }

        return $this->planningInputRepos->findNotValidated($validateInvalid, $maxNrOfInputs, $pouleStructure, $selfReferee);
    }

    /**
     * @param PlanningInput $planningInputParam
     * @param bool $resetPlanningInputWhenInvalid
     * @param array<int, bool>|null $showNrOfPlaces
     * @throws Exception
     */
    protected function validatePlanningInput(
        PlanningInput $planningInputParam,
        bool $resetPlanningInputWhenInvalid,
        array &$showNrOfPlaces = null
    ): void {
        $planningInput = $this->planningInputRepos->getFromInput($planningInputParam);

        if ($planningInput === null) {
            return;
        }
        if ($showNrOfPlaces !== null && array_key_exists($planningInput->getNrOfPlaces(), $showNrOfPlaces) === false) {
            $this->getLogger()->info("TRYING NROFPLACES: " . $planningInput->getNrOfPlaces());
            $showNrOfPlaces[$planningInput->getNrOfPlaces()] = true;
        }
        $succeededPlannings = $planningInput->getPlanningsWithState(Planning::STATE_SUCCEEDED);
        if ($succeededPlannings->count() === 0) {
            throw new \Exception("input (inputid " . ((string)$planningInput->getId()) . ") has no bestplanning", E_ERROR);
        }
        $validator = new PlanningValidator();
        foreach ($succeededPlannings as $succeededPlanning) {
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
            $planningOutput = new PlanningOutput($this->getLogger());
            $planningOutput->outputWithGames($succeededPlanning, true);
            $planningOutput->outputWithTotals($succeededPlanning, true);

            throw new \Exception(reset($validations), E_ERROR);
        }
    }

    protected function setValidity(Planning $planning, int $validity): void
    {
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
        $pouleStructureParam = $input->getOption('pouleStructure');
        if (!is_string($pouleStructureParam) || strlen($pouleStructureParam) === 0) {
            return null;
        }
        $pouleStructureParam = explode(',', $pouleStructureParam);
        $pouleStructure = [];
        foreach ($pouleStructureParam as $nrOfPlaces) {
            $pouleStructure[] = (int)$nrOfPlaces;
        }
        return new PouleStructure(...$pouleStructure);
    }

    /**
     * @param InputInterface $input
     * @return int|null
     */
    protected function getSelfReferee(InputInterface $input): ?int
    {
        $selfReferee = null;
        $optionSelfReferee = $input->getOption('selfReferee');
        if (is_string($optionSelfReferee) && strlen($optionSelfReferee) > 0 && ctype_digit($optionSelfReferee)) {
            $selfReferee = (int)$optionSelfReferee;
            if ($selfReferee !== SelfReferee::OTHERPOULES && $selfReferee !== SelfReferee::SAMEPOULE) {
                $selfReferee = SelfReferee::DISABLED;
            }
        }
        return $selfReferee;
    }

    /**
     * @param list<string> $invalidPlanningInputsMessages
     * @throws Exception
     */
    protected function sendMailWithInvalid(array $invalidPlanningInputsMessages): void
    {
        $subject = count($invalidPlanningInputsMessages) . ' invalid planningInputs';
        $this->getLogger()->info($subject);
        $stream = fopen('php://memory', 'r+');
        if ($stream === false || $this->mailer === null) {
            return;
        }
        $loggerOutput = new Logger('invalida-planninginput-logger');
        $loggerOutput->pushProcessor(new UidProcessor());
        $handler = new StreamHandler($stream, Logger::INFO);
        $loggerOutput->pushHandler($handler);

        foreach ($invalidPlanningInputsMessages as $invalidPlanningInputsMessage) {
            $loggerOutput->info($invalidPlanningInputsMessage);
            $loggerOutput->info('');
        }

        rewind($stream);
        $emailBody = stream_get_contents($stream/*$handler->getStream()*/);
        $this->mailer->sendToAdmin(
            $subject,
            $emailBody === false ? 'unable to convert stream into string' : $emailBody,
            true
        );
    }
}
