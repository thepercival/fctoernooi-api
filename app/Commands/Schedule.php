<?php

declare(strict_types=1);

namespace App\Commands;

use App\Command;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use SportsHelpers\Sport\Variant\Against as AgainstSportVariant;
use SportsPlanning\Combinations\GamePlaceStrategy;
use SportsPlanning\Schedule\Name as ScheduleName;
use SportsPlanning\Schedule\Repository as ScheduleRepository;
use Symfony\Component\Console\Input\InputInterface;

class Schedule extends Command
{
    protected ScheduleRepository $scheduleRepos;

    public function __construct(ContainerInterface $container)
    {
        $this->scheduleRepos = $container->get(ScheduleRepository::class);
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        parent::__construct($config);
    }

    protected function getNrOfPlaces(InputInterface $input): int
    {
        $nrOfPlaces = $input->getOption('nrOfPlaces');
        if (!is_string($nrOfPlaces) || strlen($nrOfPlaces) === 0) {
            throw new \Exception('incorrect nrOfPlaces "' . $nrOfPlaces . '"', E_ERROR);
        }
        $nrOfPlaces = (int)$nrOfPlaces;
        if ($nrOfPlaces === 0) {
            throw new \Exception('incorrect nrOfPlaces "' . $nrOfPlaces . '"', E_ERROR);
        }
        return $nrOfPlaces;
    }

    protected function getGamePlaceStrategy(InputInterface $input): int
    {
        $gamePlaceStrategy = $input->getOption('gamePlaceStrategy');
        if (!is_string($gamePlaceStrategy) || strlen($gamePlaceStrategy) === 0) {
            return GamePlaceStrategy::EquallyAssigned;
        }
        $gamePlaceStrategy = (int)$gamePlaceStrategy;
        if ($gamePlaceStrategy !== GamePlaceStrategy::EquallyAssigned && $gamePlaceStrategy !== GamePlaceStrategy::RandomlyAssigned) {
            throw new \Exception('incorrect gamePlaceStrategy "' . $gamePlaceStrategy . '"', E_ERROR);
        }
        return $gamePlaceStrategy;
    }

    protected function getSportsConfigName(InputInterface $input): string
    {
        $sportsConfigName = $input->getOption('sportsConfigName');
        if (!is_string($sportsConfigName) || strlen($sportsConfigName) === 0) {
            return (string)(new ScheduleName([
                                                 new AgainstSportVariant(
                                                     1,
                                                     1,
                                                     1,
                                                     0
                                                 )
                                             ]));
        }
        return $sportsConfigName;
    }

//    protected function createSchedules(Input $planningInput): void
//    {
//        $existingSchedules = $this->scheduleRepos->findByInput($planningInput);
//        $distinctNrOfPoulePlaces = $this->scheduleRepos->getDistinctNrOfPoulePlaces($planningInput);
//        if (count($existingSchedules) !== $distinctNrOfPoulePlaces) {
//            $scheduleCreatorService = new ScheduleCreatorService($this->getLogger());
//            $scheduleCreatorService->setExistingSchedules($existingSchedules);
//            $this->getLogger()->info('creating schedules .. ');
//            $schedules = $scheduleCreatorService->createSchedules($planningInput);
//            foreach ($schedules as $schedule) {
//                $this->scheduleRepos->save($schedule);
//            }
//        }
//    }

//    protected function updateSelfReferee(PlanningInput $planningInput)
//    {
//        $planning = $planningInput->getBestPlanning();
//        if ($planning === null) {
//            throw new \Exception("there should be a best planning", E_ERROR);
//        }
//
//        $firstBatch = new SelfRefereeBatch( $planning->createFirstBatch() );
//        $refereePlaceService = new RefereePlaceService($planning);
//        if (!$refereePlaceService->assign($firstBatch)) {
//            $this->getLogger()->info("refereeplaces could not be equally assigned");
//            $planning->setValidity( PlanningValidator::UNEQUALLY_ASSIGNED_REFEREEPLACES );
//
//            $planningOutput = new PlanningOutput($this->getLogger());
//            $planningOutput->outputWithGames($planning, false);
//            $planningOutput->outputWithTotals($planning, false);
//        }
//
//        $planning->setState(PlanningBase::STATE_SUCCESS);
//        $this->planningRepos->save($planning);
//
//        $planningInput->setState(PlanningInput::STATE_ALL_PLANNINGS_TRIED);
//        $this->planningInputRepos->save($planningInput);
//        $this->getLogger()->info('   update state => STATE_ALL_PLANNINGS_TRIED');
//    }
}
