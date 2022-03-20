<?php

namespace App\Export\Pdf;

use DateTimeImmutable;
use FCToernooi\Recess;
use FCToernooi\Tournament;
use League\Period\Period;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Together as TogetherGame;
use Sports\Planning\Config as PlanningConfig;
use Sports\Round\Number as RoundNumber;

class RecessHelper
{
    protected PlanningConfig $planningConfig;
    protected DateTimeImmutable $previousLastEndDateTime;

    public function __construct(RoundNumber $roundNumber)
    {
        $this->planningConfig = $roundNumber->getValidPlanningConfig();
        $this->previousLastEndDateTime = $this->getPreviousEndDateTime($roundNumber);
    }


    /**
     * @param Tournament $tournament
     * @return list<Period>
     * @throws \Exception
     */
    public function getRecesses(Tournament $tournament): array
    {
        $filteredRecesses = $tournament->getRecesses()->filter(function (Recess $recess): bool {
            return $recess->getEndDateTime()->getTimestamp() >= $this->previousLastEndDateTime->getTimestamp();
        })->toArray();
        return array_values(
            array_map(function (Recess $recess): Period {
                $startDateTime = $recess->getStartDateTime()->getTimestamp() < $this->previousLastEndDateTime->getTimestamp()
                ? $this->previousLastEndDateTime : $recess->getStartDateTime();
                return new Period($startDateTime, $recess->getEndDateTime());
            }, $filteredRecesses)
        );
    }

    private function getPreviousEndDateTime(RoundNumber $roundNumber): DateTimeImmutable
    {
        $previous = $roundNumber->getPrevious();
        if ($previous === null) {
            return $roundNumber->getCompetition()->getStartDateTime();
        }
        $nrOfMinutesToAdd = $previous->getValidPlanningConfig()->getMaxNrOfMinutesPerGame();
        return $previous->getLastStartDateTime()->modify('+' .  $nrOfMinutesToAdd . ' minutes');
    }

    /**
     * @param AgainstGame|TogetherGame $game
     * @param list<Period> $recesses
     * @return Period|null
     */
    public function removeRecessBeforeGame(AgainstGame|TogetherGame $game, array& $recesses): Period | null
    {
        if (!$this->planningConfig->getEnableTime()) {
            return null;
        }
        $filteredRecesses = array_filter($recesses, function (Period $recess) use ($game): bool {
            return $game->getStartDateTime()->getTimestamp() === $recess->getEndDate()->getTimestamp();
        });
        $filteredRecess = reset($filteredRecesses);
        if ($filteredRecess === false) {
            return null;
        }
        $idx = array_search($filteredRecess, $recesses, true);
        if ($idx === false) {
            throw new \Exception('recess should always be found', E_ERROR);
        }
        array_splice($recesses, $idx, 1);
        return $filteredRecess;
    }
}