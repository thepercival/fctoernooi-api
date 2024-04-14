<?php

declare(strict_types=1);

namespace FCToernooi\Planning\Totals;

use DateTimeImmutable;
use FCToernooi\Planning\RoundNumberWithPlanning;
use League\Period\Period;
use Sports\Round\Number as RoundNumber;
use Sports\Round\Number\PlanningAssigner;
use Sports\Round\Number\PlanningScheduler;
use SportsPlanning\Planning;

class TotalPeriodCalculator
{
    /**
     * @param DateTimeImmutable $startDateTime
     * @param list<RoundNumberWithMinNrOfBatches> $roundNumbersWithMinNrOfBatches
     * @param list<Period> $recessPeriods
     * @return Period
     */
    public function calculate(
        DateTimeImmutable $startDateTime,
        array $roundNumbersWithMinNrOfBatches,
        array $recessPeriods): Period
    {
        $scheduler = new PlanningScheduler($recessPeriods);
        $endDateTime = clone $startDateTime;
        foreach( $roundNumbersWithMinNrOfBatches as $roundNumberWithMinNrOfBatches)
        {
            $roundNumber = $roundNumberWithMinNrOfBatches->roundNumber;
            $planningConfig = $roundNumber->getValidPlanningConfig();
            if( $roundNumber->getNumber() > 1 ) {
                $endDateTime = $endDateTime->add(new \DateInterval('PT' . $planningConfig->getMinutesAfter() . 'M'));
            }
            $nrOfBatches = $roundNumberWithMinNrOfBatches->minNrOfBatches;
            for( $batchNr = 1 ; $batchNr <= $nrOfBatches ; $batchNr++ ) {

                if( $batchNr > 1) {
                    $interval = new \DateInterval('PT' . $planningConfig->getMinutesBetweenGames() . 'M');
                    $nextGameStartDateTime = $endDateTime->add($interval);
                } else {
                    $nextGameStartDateTime = clone $endDateTime;
                }

                $nextGamePeriod = $scheduler->createGamePeriod($nextGameStartDateTime, $planningConfig);
                $nextGameStartDateTime = $scheduler->moveToFirstAvailableSlot($nextGamePeriod)->getStartDate();

                $interval = new \DateInterval('PT' . $planningConfig->getMaxNrOfMinutesPerGame() . 'M');
                $endDateTime = $nextGameStartDateTime->add($interval);
            }
        }
        return new Period( $startDateTime, $endDateTime );
    }

    /**
     * @param RoundNumber $roundNumber
     * @param Planning $bestPlanning
     * @param list<Period> $recessPeriods
     * @throws \SportsPlanning\Exception\NoBestPlanning
     */
    public function assignGames(
        RoundNumber $roundNumber,
        Planning $bestPlanning,
        array $recessPeriods,
    ): void
    {
        // while ($roundNumber) {
//            $planningInput = (new PlanningInputCreator())->create($roundNumber, $nrOfReferees);
//
//            $input = $this->inputRepository->getFromInput($planningInput);
//            if ($input === null) {
//                return false;
//            }
//            $bestPlanning = $input->getBestPlanning(null);

        $planningAssigner = new PlanningAssigner(new PlanningScheduler($recessPeriods));
        $planningAssigner->assignPlanningToRoundNumber($roundNumber, $bestPlanning);
        // return $planningAssigner->unusedRecessPeriods();
    }

    // je bent hier de hyrarchie nodig en daarnaast moet je weten per ronde
    // hoeveel wat de minimum en maximum te spelen wedstrijden zijn
}
