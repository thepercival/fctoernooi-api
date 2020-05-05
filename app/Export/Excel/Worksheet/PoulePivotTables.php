<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 15:03
 */

namespace App\Export\Excel\Worksheet;

use App\Export\Excel\Spreadsheet;
use App\Export\Pdf\Page as ToernooiPdfPage;
use Voetbal\Game;
use Voetbal\Place;
use Voetbal\Ranking\Service as RankingService;
use Voetbal\Round;
use Voetbal\Poule;
use Voetbal\NameService;
use App\Export\Excel\Worksheet as FCToernooiWorksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Voetbal\Round\Number as RoundNumber;
use Voetbal\Sport\ScoreConfig\Service as SportScoreConfigService;
use Voetbal\State;

class PoulePivotTables extends FCToernooiWorksheet
{
    /**
     * @var SportScoreConfigService
     */
    protected $sportScoreConfigService;
    /**
     * @var int
     */
    protected $maxNrOfColumns;

    const WIDTH_COLUMN = 4;
    const BORDER_COLOR = 'black';

    public function __construct(Spreadsheet $parent = null)
    {
        parent::__construct($parent, 'draaitabellen');
        $parent->addSheet($this, Spreadsheet::INDEX_POULEPIVOTTABLES);
        $this->sportScoreConfigService = new SportScoreConfigService();
        $this->setCustomHeader();
    }

    public function draw()
    {
        $firstRoundNumber = $this->getParent()->getStructure()->getFirstRoundNumber();
        $row = 1;
        $this->drawRoundNumber($firstRoundNumber, $row);
        for ($columnNr = 1; $columnNr <= $this->getMaxNrOfColumns(); $columnNr++) {
            $this->getColumnDimensionByColumn($columnNr)->setAutoSize(true);
        }
    }

    protected function getMaxNrOfColumns(): int
    {
        if ($this->maxNrOfColumns !== null) {
            return $this->maxNrOfColumns;
        }
        $firstRoundNumber = $this->getParent()->getStructure()->getFirstRoundNumber();
        $this->maxNrOfColumns = $this->getMaxNrOfColumnsHelper($firstRoundNumber, 0);
        return $this->maxNrOfColumns;
    }

    protected function getMaxNrOfColumnsHelper(RoundNumber $roundNumber, int $maxNrOfColumns): int
    {
        $maxNrOfPoulePlaces = 0;
        foreach ($roundNumber->getPoules() as $poule) {
            $nrOfPoulePlaces = $poule->getPlaces()->count();
            if ($nrOfPoulePlaces > $maxNrOfPoulePlaces) {
                $maxNrOfPoulePlaces = $nrOfPoulePlaces;
            }
        }
        $maxNrOfColumnsRoundNumber = $this->maxNrOfColumns = 1 + $maxNrOfPoulePlaces + 1 + 1;
        if ($maxNrOfColumnsRoundNumber > $maxNrOfColumns) {
            $maxNrOfColumns = $maxNrOfColumnsRoundNumber;
        }

        if ($roundNumber->hasNext()) {
            return $this->getMaxNrOfColumnsHelper($roundNumber->getNext(), $maxNrOfColumns);
        }
        return $maxNrOfColumns;
    }

    protected function drawRoundNumber(RoundNumber $roundNumber, int $row)
    {
        $subHeader = $this->getParent()->getNameService()->getRoundNumberName($roundNumber);
        $row = $this->drawSubHeader($row, $subHeader, 1, $this->getMaxNrOfColumns(), true);
        foreach ($roundNumber->getPoules() as $poule) {
            if (!$poule->needsRanking()) {
                continue;
            }
            $row = $this->drawPoule($poule, $row);
        }

        if ($roundNumber->hasNext()) {
            $this->drawRoundNumber($roundNumber->getNext(), $row);
        }
    }

    public function drawPoule(Poule $poule, int $row): int
    {
        $row = $this->drawPouleHeader($poule, $row);
        $row = $this->drawPouleContent($poule, $row);
        return $row;
    }

    public function drawPouleHeader(Poule $poule, int $row): int
    {
        $column = 1;
        $pouleName = $this->getParent()->getNameService()->getPouleName($poule, true);
        $cell = $this->getCellByColumnAndRow($column, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $cell->setValue($pouleName);

        foreach ($poule->getPlaces() as $place) {
            $cell = $this->getCellByColumnAndRow(++$column, $row);
            $placeName = $this->getParent()->getNameService()->getPlaceFromName($place, true);
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $cell->setValue($placeName);
        }
        $cell = $this->getCellByColumnAndRow(++$column, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $cell->setValue("pnt");

        $cell = $this->getCellByColumnAndRow(++$column, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $cell->setValue("plek");

        return $row + 1;
    }

    public function drawPouleContent(Poule $poule, int $row): int
    {
        $rowStart = $row;
        $columnStart = 1;
        $pouleState = $poule->getState();
        $competition = $this->getParent()->getTournament()->getCompetition();
        $rankingItems = null;
        if ($pouleState === State::Finished) {
            $rankingService = new RankingService($poule->getRound(), $competition->getRuleSet());
            $rankingItems = $rankingService->getItemsForPoule($poule);
        }
        $nrOfPlaces = $poule->getPlaces()->count();

        $columnEnd = $columnStart;
        foreach ($poule->getPlaces() as $place) {
            $column = $columnStart;
            $placeName = $this->getParent()->getNameService()->getPlaceFromName($place, true);
            $cell = $this->getCellByColumnAndRow($column++, $row);
            $cell->setValue($placeName);

            $placeGames = $poule->getGames()->filter(
                function (Game $game) use ($place): bool {
                    return $game->isParticipating($place);
                }
            )->toArray();
            // draw versus
            for ($placeNr = 1; $placeNr <= $nrOfPlaces; $placeNr++) {
                $cell = $this->getCellByColumnAndRow($column++, $row);
                if ($poule->getPlace($placeNr) === $place) {
                    $this->fill($cell->getStyle(), 'DDDDDD');
                }
                $score = '';
                if ($pouleState !== State::Created) {
                    $score = $this->getScore($place, $poule->getPlace($placeNr), $placeGames);
                }
                $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $cell->setValue($score);
            }

            $rankingItem = null;
            if ($rankingItems !== null) {
                $arrFoundRankingItems = array_filter(
                    $rankingItems,
                    function ($rankingItem) use ($place): bool {
                        return $rankingItem->getPlace() === $place;
                    }
                );
                $rankingItem = reset($arrFoundRankingItems);
            }

            // draw pointsrectangle
            $points = ($rankingItem !== null) ? $rankingItem->getUnranked()->getPoints() : '';
            $cell = $this->getCellByColumnAndRow($column++, $row);
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $cell->setValue($points);

            // draw rankrectangle
            $rank = ($rankingItem !== null) ? $rankingItem->getUniqueRank() : '';
            $cell = $this->getCellByColumnAndRow($column++, $row);
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $cell->setValue($rank);

            $row++;
            $columnEnd = $column;
        }
        $range = $this->range($columnStart, $rowStart - 1, $columnEnd - 1, $row - 1);
        $this->border($this->getStyle($range), 'allBorders');

        return $row + 2;
    }

    protected function getScore(Place $homePlace, Place $awayPlace, array $placeGames): string
    {
        $foundHomeGames = array_filter(
            $placeGames,
            function ($game) use ($homePlace, $awayPlace): bool {
                return $game->isParticipating($awayPlace, Game::AWAY) && $game->isParticipating($homePlace, Game::HOME);
            }
        );
        if (count($foundHomeGames) > 1) {
            return '';
        }
        if (count($foundHomeGames) === 1) {
            return $this->getGameScore(reset($foundHomeGames), false);
        }
        $foundAwayGames = array_filter(
            $placeGames,
            function ($game) use ($homePlace, $awayPlace): bool {
                return $game->isParticipating($homePlace, Game::AWAY) && $game->isParticipating($awayPlace, Game::HOME);
            }
        );
        if (count($foundAwayGames) !== 1) {
            return '';
        }
        return $this->getGameScore(reset($foundAwayGames), true);
    }

    protected function getGameScore(Game $game, bool $reverse): string
    {
        $score = ' - ';
        if ($game->getState() !== State::Finished) {
            return $score;
        }
        $finalScore = $this->sportScoreConfigService->getFinalScore($game);
        if ($finalScore === null) {
            return $score;
        }
        if ($reverse === true) {
            return $finalScore->getAway() . $score . $finalScore->getHome();
        }
        return $finalScore->getHome() . $score . $finalScore->getAway();
    }
}
