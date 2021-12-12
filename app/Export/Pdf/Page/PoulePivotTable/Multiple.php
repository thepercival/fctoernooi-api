<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page\PoulePivotTable;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document;
use App\Export\Pdf\Page as ToernooiPdfPage;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Place;
use Sports\Poule;
use Sports\Ranking\Calculator\Round as RoundRankingCalculator;
use Sports\Ranking\Item\Round as RoundRankingItem;
use Sports\Ranking\Item\Round\Sport as SportRoundRankingItem;
use Sports\Round\Number as RoundNumber;
use Sports\State;

class Multiple extends ToernooiPdfPage
{
    use Helper;
    protected float $nameColumnWidth;
    protected float $pointsColumnWidth;
    protected float $rankColumnWidth;
    protected float $versusColumnsWidth;
    // protected $maxPoulesPerLine;
    // protected $placeWidthStructure;
    // protected $pouleMarginStructure;
    protected int $rowHeight;

    public function __construct(Document $document, mixed $param1)
    {
        parent::__construct($document, $param1);
        $this->setLineWidth(0.5);
        $this->nameColumnWidth = $this->getDisplayWidth() * 0.25;
        $this->versusColumnsWidth = $this->getDisplayWidth() * 0.62;
        $this->pointsColumnWidth = $this->getDisplayWidth() * 0.08;
        $this->rankColumnWidth = $this->getDisplayWidth() * 0.05;
        /*$this->maxPoulesPerLine = 3;
        $this->placeWidthStructure = 30;
        $this->pouleMarginStructure = 10;*/
        $this->rowHeight = 18;
    }

    public function getPageMargin(): float
    {
        return 20;
    }

    public function getHeaderHeight(): float
    {
        return 0;
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
        $fontHeightSubHeader = $this->getParent()->getFontHeightSubHeader();
        $this->setFont($this->getParent()->getFont(true), $this->getParent()->getFontHeightSubHeader());
        $x = $this->getPageMargin();
        $displayWidth = $this->getDisplayWidth();
        $subHeader = $this->getParent()->getNameService()->getRoundNumberName($roundNumber);
        $subHeader .= ' - totaalstand';
        $this->drawCell($subHeader, $x, $y, $displayWidth, $fontHeightSubHeader, Align::Center);
        $this->setFont($this->getParent()->getFont(), $this->getParent()->getFontHeight());
        return $y - (2 * $fontHeightSubHeader);
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
        $height += $this->rowHeight * $nrOfPlaces;

        return $height;
    }



    public function draw(Poule $poule, float $y): float
    {
        // draw first row
        $y = $this->drawPouleHeader($poule, $y);

        $competitionSports = $poule->getCompetition()->getSports();
        $sportColumnWidth = $this->versusColumnsWidth / $competitionSports->count();

        $pouleState = $poule->getState();
        $roundRankingItems = null;
        if ($pouleState === State::Finished) {
            $roundRankingItems = (new RoundRankingCalculator())->getItemsForPoule($poule);
        }

        $height = $this->rowHeight;
        $nameService = $this->getParent()->getNameService();

        foreach ($poule->getPlaces() as $place) {
            $x = $this->getPageMargin();
            // placename
            {
                $placeName = $place->getPlaceNr() . '. ' . $nameService->getPlaceFromName($place, true);
                $this->setFont($this->getParent()->getFont(), $this->getPlaceFontHeight($placeName));
                $x = $this->drawCellCustom($placeName, $x, $y, $this->nameColumnWidth, $height, Align::Left);
                $this->setFont($this->getParent()->getFont(), $this->getParent()->getFontHeight());
            }

            foreach ($poule->getCompetition()->getSports() as $competitionSport) {
                $sportRoundRankingItem = null;
                if ($poule->getState($competitionSport) === State::Finished) {
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

        $x = $this->getPageMargin();
        $pouleName = $this->getParent()->getNameService()->getPouleName($poule, true);
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
