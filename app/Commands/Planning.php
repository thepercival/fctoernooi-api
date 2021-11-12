<?php
declare(strict_types=1);

namespace App\Commands;

use App\Command;
use FCToernooi\Tournament\CustomPlaceRanges as TournamentStructureRanges;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use SportsHelpers\PlaceRanges;
use SportsHelpers\SportRange;
use SportsPlanning\Input;
use SportsPlanning\Input\Repository as PlanningInputRepository;
use SportsPlanning\Planning\Repository as PlanningRepository;
use SportsPlanning\Schedule\Creator\Service as ScheduleCreatorService;
use SportsPlanning\Schedule\Repository as ScheduleRepository;
use Symfony\Component\Console\Input\InputInterface;

class Planning extends Command
{
    protected PlanningInputRepository $planningInputRepos;
    protected PlanningRepository $planningRepos;
    protected ScheduleRepository $scheduleRepos;

    public function __construct(ContainerInterface $container)
    {
        $this->planningInputRepos = $container->get(PlanningInputRepository::class);
        $this->planningRepos = $container->get(PlanningRepository::class);
        $this->scheduleRepos = $container->get(ScheduleRepository::class);
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        parent::__construct($config);
    }

    protected function getPlacesRange(InputInterface $input): SportRange
    {
        $placeRange = $this->getInputRange($input, 'placesRange');
        if ($placeRange !== null) {
            return $placeRange;
        }
        return new SportRange(
            PlaceRanges::MinNrOfPlacesPerPoule, TournamentStructureRanges::MaxNrOfPlacesPerRoundSmall
        );
    }

    protected function getInputRange(InputInterface $input, string $paramName): SportRange|null
    {
        $rangeOption = $input->getOption($paramName);
        if (!is_string($rangeOption) || strlen($rangeOption) === 0) {
            return null;
        }
        if (!str_contains($rangeOption, '-')) {
            throw new \Exception('misformat "' . $rangeOption . '"-option', E_ERROR);
        }
        $minMax = explode('-', $rangeOption);
        return new SportRange((int)$minMax[0], (int)$minMax[1]);
    }

    protected function createSchedules(Input $planningInput): void
    {
        $existingSchedules = $this->scheduleRepos->findByInput($planningInput);
        $distinctNrOfPoulePlaces = $this->scheduleRepos->getDistinctNrOfPoulePlaces($planningInput);
        if (count($existingSchedules) !== $distinctNrOfPoulePlaces) {
            $scheduleCreatorService = new ScheduleCreatorService($this->getLogger());
            $scheduleCreatorService->setExistingSchedules($existingSchedules);
            $this->getLogger()->info('creating schedules .. ');
            $schedules = $scheduleCreatorService->createSchedules($planningInput);
            foreach ($schedules as $schedule) {
                $this->scheduleRepos->save($schedule);
            }
        }
    }

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
