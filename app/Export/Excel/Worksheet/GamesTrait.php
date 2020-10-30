<?php

declare(strict_types=1);

namespace App\Export\Excel\Worksheet;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Sports\Game;
use Sports\Round\Number as RoundNumber;
use App\Export\Pdf\Page;
use Sports\State;

trait GamesTrait
{
    protected $hasReferees;
    protected $selfRefereesAssigned;
    protected $refereesAssigned;

    protected function drawSubHeaderHelper(int $rowStart, string $title, int $colStart = null, int $colEnd = null): int
    {
        if ($colStart === null) {
            $colStart = 1;
        }
        if ($colEnd === null) {
            $colEnd = Planning::NR_OF_COLUMNS;
        }
        return parent::drawSubHeader($rowStart, $title, $colStart, $colEnd);
    }

    public function setSelfRefereesAssigned(bool $selfRefereesAssigned)
    {
        $this->selfRefereesAssigned = $selfRefereesAssigned;
    }

    public function setRefereesAssigned(bool $refereesAssigned)
    {
        $this->refereesAssigned = $refereesAssigned;
    }

    public function drawGamesHeader(RoundNumber $roundNumber, int $row): int
    {
        $cell = $this->getCellByColumnAndRow(Planning::COLUMN_POULE, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $cell->setValue($roundNumber->needsRanking() ? "p." : "vs");

        $cell = $this->getCellByColumnAndRow(Planning::COLUMN_START, $row);
        if ($roundNumber->getValidPlanningConfig()->getEnableTime()) {
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $cell->setValue($this->getParent()->gamesOnSameDay($roundNumber) ? "tijd" : "datum tijd");
        }

        $cell = $this->getCellByColumnAndRow(Planning::COLUMN_FIELD, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $cell->setValue("v.");

        $cell = $this->getCellByColumnAndRow(Planning::COLUMN_HOME, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $cell->setValue("thuis");

        $cell = $this->getCellByColumnAndRow(Planning::COLUMN_SCORE, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $cell->setValue("score");

        $cell = $this->getCellByColumnAndRow(Planning::COLUMN_AWAY, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $cell->setValue("uit");

        if ($this->refereesAssigned || $this->selfRefereesAssigned) {
            $cell = $this->getCellByColumnAndRow(Planning::COLUMN_REFEREE, $row);
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $cell->setValue($this->selfRefereesAssigned ? 'scheidsrechter' : 'sch.');
        }
        return $row + 2;
    }

    public function drawBreak(RoundNumber $roundNumber, int $row)
    {
        $cell = $this->getCellByColumnAndRow(Planning::COLUMN_START, $row);
        if ($roundNumber->getValidPlanningConfig()->getEnableTime()) {
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $cell->setValue($this->getDateTime($roundNumber, $this->tournamentBreak->getStartDate()));
        }
        $cell = $this->getCellByColumnAndRow(Planning::COLUMN_SCORE, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $cell->setValue("PAUZE");
        $this->drewbreak = true;
        return $row + 1;
    }

    /**
     * @param Game $game
     * @param int $row
     * @param bool $striped
     * @return int
     */
    public function drawGame(Game $game, int $row, bool $striped = true): int
    {
        if (($game->getBatchNr() % 2) === 0 && $striped === true) {
            $range = $this->range(Planning::COLUMN_POULE, $row, Planning::NR_OF_COLUMNS, $row);
            $this->fill($this->getStyle($range), 'EEEEEE');
        }

        $pouleName = $this->getParent()->getNameService()->getPouleName($game->getPoule(), false);
        $cell = $this->getCellByColumnAndRow(Planning::COLUMN_POULE, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $cell->setValue($pouleName);

//        $nX = $this->getPageMargin();
//        $nRowHeight = $this->getRowHeight();
        $roundNumber = $game->getRound()->getNumber();
//
        $cell = $this->getCellByColumnAndRow(Planning::COLUMN_START, $row);
        if ($roundNumber->getValidPlanningConfig()->getEnableTime()) {
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $cell->setValue($this->getDateTime($roundNumber, $game->getStartDateTime()));
        }

        $cell = $this->getCellByColumnAndRow(Planning::COLUMN_FIELD, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $cell->setValue($game->getField()->getName());

        $home = $this->getParent()->getNameService()->getPlacesFromName($game->getPlaces(Game::HOME), true, true);
        $cell = $this->getCellByColumnAndRow(Planning::COLUMN_HOME, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $cell->setValue($home);

        $cell = $this->getCellByColumnAndRow(Planning::COLUMN_SCORE, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $cell->setValue($this->getScore($game));

        $away = $this->getParent()->getNameService()->getPlacesFromName($game->getPlaces(Game::AWAY), true, true);
        $cell = $this->getCellByColumnAndRow(Planning::COLUMN_AWAY, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $cell->setValue($away);

        $cell = $this->getCellByColumnAndRow(Planning::COLUMN_REFEREE, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        if ($game->getReferee() !== null) {
            $cell->setValue($game->getReferee()->getInitials());
        } else {
            if ($game->getRefereePlace() !== null) {
                $placeRef = $this->getParent()->getNameService()->getPlaceName($game->getRefereePlace(), true, true);
                $cell->setValue($placeRef);
            }
        }
        return $row + 1;
    }

    protected function getDateTime(RoundNumber $roundNumber, \DateTimeImmutable $dateTime): string
    {
        $localDateTime = $dateTime->setTimezone(new \DateTimeZone('Europe/Amsterdam'));
        $text = $localDateTime->format("H:i");
        if ($this->getParent()->gamesOnSameDay($roundNumber)) {
            return $text;
        }
        //                $df = new \IntlDateFormatter('nl_NL',\IntlDateFormatter::LONG, \IntlDateFormatter::NONE,'Europe/Oslo');
        //                $dateElements = explode(" ", $df->format($game->getStartDateTime()));
        //                $month = strtolower( substr( $dateElements[1], 0, 3 ) );
        //                $text = $game->getStartDateTime()->format("d") . " " . $month . " ";
        return $localDateTime->format("d-m ") . $text;
    }

    protected function getScore(Game $game): string
    {
        $score = ' - ';
        if ($game->getState() !== State::Finished) {
            return $score;
        }
        $finalScore = $this->sportScoreConfigService->getFinalScore($game);
        if ($finalScore === null) {
            return $score;
        }
        $extension = $game->getFinalPhase() === Game::PHASE_EXTRATIME ? '*' : '';
        return $finalScore->getHome() . $score . $finalScore->getAway() . $extension;
    }
}
