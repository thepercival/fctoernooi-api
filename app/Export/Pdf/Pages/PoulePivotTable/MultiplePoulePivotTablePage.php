<?php

declare(strict_types=1);

namespace App\Export\Pdf\Pages\PoulePivotTable;

use App\Export\Pdf\Align;
use App\Export\Pdf\Documents\PoulePivotTablesDocument as PoulePivotTablesDocument;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Game\State as GameState;
use Sports\Place;
use Sports\Poule;
use Sports\Ranking\Calculator\Round as RoundRankingCalculator;
use Sports\Ranking\Item\Round as RoundRankingItem;
use Sports\Ranking\Item\Round\Sport as SportRoundRankingItem;
use Sports\Round\Number as RoundNumber;

class MultiplePoulePivotTablePage extends ToernooiPdfPage
{
    use Helper;

    protected float $nameColumnWidth;
    protected float $pointsColumnWidth;
    protected float $rankColumnWidth;
    protected float $versusColumnsWidth;
    // protected $maxPoulesPerLine;
    // protected $placeWidthStructure;
    // protected $pouleMarginStructure;

    public function __construct(PoulePivotTablesDocument $document, mixed $param1)
    {
        parent::__construct($document, $param1);
        $this->setFont($this->helper->getTimesFont(), $this->parent->getConfig()->getFontHeight());
        $this->setLineWidth(0.5);
        $this->nameColumnWidth = $this->getDisplayWidth() * 0.25;
        $this->versusColumnsWidth = $this->getDisplayWidth() * 0.62;
        $this->pointsColumnWidth = $this->getDisplayWidth() * 0.08;
        $this->rankColumnWidth = $this->getDisplayWidth() * 0.05;
    }

    /*public function draw()
    {
        $tournament = $this->getParent()->getTournament();
        $firstRound = $this->getParent()->getStructure()->getRootRound();
        $this->setQual( $firstRound );
        $y = $this->drawHeader( "draaitabel per poule" );
        $y = $this->draw( $firstRound, $y );
    }*/

    /*protected function setQual( Round $parentRound )
    {
        foreach ($parentRound->getChildRounds() as $childRound) {
            $qualifyService = new QualifyService($childRound);
            $qualifyService->setQualifyRules();
            $this->setQual( $childRound );
        }
    }*/

    public function drawPageStartHeader(RoundNumber $roundNumber, float $y): float
    {
        $roundNumberHeaderHeight = $this->parent->getGamesConfig()->getRoundNumberHeaderHeight();
        $roundNumberHeaderFontHeight = $this->parent->getGamesConfig()->getRoundNumberHeaderHeight();
        $this->setFont($this->helper->getTimesFont(true), $roundNumberHeaderFontHeight);
        $x = self::PAGEMARGIN;
        $displayWidth = $this->getDisplayWidth();
        $subHeader = $this->getStructureNameService()->getRoundNumberName($roundNumber);
        $subHeader .= ' - totaalstand';
        $cell = new Rectangle(
            new HorizontalLine(new Point($x, $y), $displayWidth),
            $roundNumberHeaderHeight
        );
        $this->drawCell($subHeader, $cell, Align::Center);
        $this->setFont($this->helper->getTimesFont(), $roundNumberHeaderFontHeight);
        return $y - (2 * $roundNumberHeaderHeight);
    }

    // t/m 3 places 0g, t/m 8 places 45g, hoger 90g
    public function getPouleHeight(Poule $poule): float
    {
        $nrOfSports = $poule->getCompetition()->getSports()->count();
        $nrOfPlaces = $poule->getPlaces()->count();

        // header row
        $versusColumnWidth = $this->versusColumnsWidth / $nrOfPlaces;
        $degrees = $this->getVersusHeaderDegrees($nrOfSports);
        $height = $this->getVersusHeight($versusColumnWidth, $degrees);

        // places
        $height += $this->parent->getConfig()->getRowHeight() * $nrOfPlaces;

        return $height;
    }



    public function draw(Poule $poule, float $y): float
    {
        // draw first row
        $y = $this->drawPouleHeader($poule, $y);

        $competitionSports = $poule->getCompetition()->getSports();
        $sportColumnWidth = $this->versusColumnsWidth / $competitionSports->count();

        $pouleState = $poule->getGamesState();
        $roundRankingItems = null;
        if ($pouleState === GameState::Finished) {
            $roundRankingItems = (new RoundRankingCalculator())->getItemsForPoule($poule);
        }

        $height = $this->parent->getConfig()->getRowHeight();
        $nameService = $this->getStructureNameService();

        foreach ($poule->getPlaces() as $place) {
            $x = self::PAGEMARGIN;
            // placename
            {
                $placeName = $place->getPlaceNr() . '. ' . $nameService->getPlaceFromName($place, true);
                $this->setFont($this->helper->getTimesFont(), $this->getPlaceFontHeight($placeName));
                $x = $this->drawCellCustom($placeName, $x, $y, $this->nameColumnWidth, $height, Align::Left);
                $this->setFont($this->helper->getTimesFont(), $this->parent->getFontHeight());
            }

            foreach ($poule->getCompetition()->getSports() as $competitionSport) {
                $sportRoundRankingItem = null;
                if ($poule->getGamesState($competitionSport) === GameState::Finished) {
                    $calculator = $this->getSportRankingCalculator($competitionSport);
                    $sportRankingItems = $calculator->getItemsForPoule($poule);
                    $sportRoundRankingItem = $this->getSportRankingItemByPlace($sportRankingItems, $place);
                }

                $sportRank = '';
                if ($sportRoundRankingItem !== null) {
                    $sportRank = (string)$sportRoundRankingItem->getRank();
                }
                $x = $this->drawCellCustom($sportRank, $x, $y, $sportColumnWidth, $height, Align::Right);
            }

            // total and rank
            $roundRankingItem = $this->getRankingItem($place, $roundRankingItems);
            $cumRank = $roundRankingItem !== null ? (string)$roundRankingItem->getCumulativeRank() : '';
            $x = $this->drawCellCustom($cumRank, $x, $y, $this->pointsColumnWidth, $height, Align::Right);

            $rank = $roundRankingItem !== null ? (string)$roundRankingItem->getRank() : '';
            $this->drawCellCustom($rank, $x, $y, $this->rankColumnWidth, $height, Align::Right);

            $y -= $height;
        }
        return $y - $height;
    }

    /**
     * @param list<SportRoundRankingItem> $sportRankingItems
     * @param Place $place
     * @return SportRoundRankingItem|null
     */
    protected function getSportRankingItemByPlace(array $sportRankingItems, Place $place): SportRoundRankingItem|null
    {
        $filtered = array_filter($sportRankingItems, fn (SportRoundRankingItem $item) => $item->getPerformance()->getPlace() === $place);
        $item = reset($filtered);
        return $item === false ? null : $item;
    }

    protected function drawPouleHeader(Poule $poule, float $y): float
    {
        $nrOfSports = $poule->getCompetition()->getSports()->count();
        $versusColumnWidth = $this->versusColumnsWidth / $nrOfSports;
        $degrees = $this->getVersusHeaderDegrees($nrOfSports);
        $height = $this->getVersusHeight($versusColumnWidth, $degrees);

        $x = self::PAGEMARGIN;
        $pouleName = $this->getStructureNameService()->getPouleName($poule, true);
        $x = $this->drawHeaderCustom($pouleName, $x, $y, $this->nameColumnWidth, $height);
        $x = $this->drawVersusHeader($poule, $x, $y, $degrees);

        // draw pointsrectangle
        $x = $this->drawHeaderCustom('totaal', $x, $y, $this->pointsColumnWidth, $height);
        // draw rankrectangle
        $x = $this->drawHeaderCustom('plek', $x, $y, $this->rankColumnWidth, $height);

        return $y - $height;
    }

    protected function drawVersusHeader(Poule $poule, float $x, float $y, int $degrees): float
    {
        $competitionSports = $poule->getCompetition()->getSports();
        $versusColumnWidth = $this->versusColumnsWidth / $competitionSports->count();
        $height = $this->getVersusHeight($versusColumnWidth, $degrees);

        foreach ($competitionSports as $competitionSport) {
            $sportName = $competitionSport->getSport()->getName();
            $x = $this->drawHeaderCustom($sportName, $x, $y, $versusColumnWidth, $height, $degrees);
        }
        return $x;
    }
//
//    protected function drawVersusCell(Place $place, GameAmountConfig $gameAmountConfig, float $x, float $y): float
//    {
//        $columnWidth = $this->versusColumnsWidth / $gameAmountConfig->getAmount();
//        for ($gameRoundNumber = 1 ; $gameRoundNumber <= $gameAmountConfig->getAmount() ; $gameRoundNumber++) {
//            $score = $this->getScore($place, $gameRoundNumber);
//            $x = $this->drawCellCustom($score, $x, $y, $columnWidth, $this->rowHeight, Align::Center);
//        }
//        return $x;
//    }
//
//    protected function getScore(Place $place, int $gameRoundNumber): string
//    {
//        $gamePlace = $this->getGamePlace($place, $gameRoundNumber);
//        if ($gamePlace === null) {
//            return '';
//        }
//        if ($gamePlace->getGame()->getState() !== State::Finished) {
//            return '';
//        }
//        return (string)$this->scoreConfigService->getFinalTogetherScore($gamePlace);
//    }
//
//    protected function getGamePlace(Place $place, int $gameRoundNumber): TogetherGamePlace|null
//    {
//        $foundGames = array_filter(
//            $place->getTogetherGamePlaces(),
//            function (TogetherGamePlace $gamePlace) use ($gameRoundNumber): bool {
//                return $gamePlace->getGameRoundNumber() === $gameRoundNumber;
//            }
//        );
//        $foundGame = reset($foundGames);
//        return $foundGame === false ? null : $foundGame;
//    }

    /**
     * @param Place $place
     * @param list<RoundRankingItem>|null $rankingItems
     * @return RoundRankingItem|null
     * @throws \Exception
     */
    protected function getRankingItem(Place $place, array|null $rankingItems): RoundRankingItem|null
    {
        if ($rankingItems === null) {
            return null;
        }

        $arrFoundRankingItems = array_filter(
            $rankingItems,
            function (RoundRankingItem $rankingItem) use ($place): bool {
                return $rankingItem->getPlace() === $place;
            }
        );
        $rankingItem = reset($arrFoundRankingItems);
        return $rankingItem === false ? null : $rankingItem;
    }

    protected function getRank(RoundRankingItem|null $rankingItem, CompetitionSport $competitionSport): string
    {
        if ($rankingItem === null) {
            return '';
        }
        $sportRankingItem = $rankingItem->getSportItem($competitionSport);
        return (string)$sportRankingItem->getUniqueRank();
    }
}
