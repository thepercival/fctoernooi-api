<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Page\PoulePivotTable\Helper;
use Exception;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Place;
use Sports\Planning\GameAmountConfig;
use Sports\Poule;
use Sports\Ranking\Item\Round\Sport as SportRoundRankingItem;
use Sports\Round\Number as RoundNumber;
use Sports\Score\Config\Service as ScoreConfigService;
use Sports\State;

abstract class PoulePivotTables extends ToernooiPdfPage
{
    use Helper;
    protected ScoreConfigService $scoreConfigService;
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
        $this->scoreConfigService = new ScoreConfigService();
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

    public function drawPageStartHeader(RoundNumber $roundNumber, CompetitionSport $competitionSport, float $y): float
    {
        $fontHeightSubHeader = $this->getParent()->getFontHeightSubHeader();
        $this->setFont($this->getParent()->getFont(true), $this->getParent()->getFontHeightSubHeader());
        $x = $this->getPageMargin();
        $displayWidth = $this->getDisplayWidth();
        $subHeader = $this->getParent()->getNameService()->getRoundNumberName($roundNumber);
        $subHeader .= ' - ' . $competitionSport->getSport()->getName();
        $this->drawCell($subHeader, $x, $y, $displayWidth, $fontHeightSubHeader, Align::Center);
        $this->setFont($this->getParent()->getFont(), $this->getParent()->getFontHeight());
        return $y - (2 * $fontHeightSubHeader);
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

    protected function drawPouleHeaderHelper(Poule $poule, GameAmountConfig $gameAmountConfig, float $y, int $degrees = 0): float
    {
        $nrOfPlaces = $poule->getPlaces()->count();
        $versusColumnWidth = $this->versusColumnsWidth / $nrOfPlaces;
        $height = $this->getVersusHeight($versusColumnWidth, $degrees);

        $x = $this->getPageMargin();
        $pouleName = $this->getParent()->getNameService()->getPouleName($poule, true);
        $x = $this->drawHeaderCustom($pouleName, $x, $y, $this->nameColumnWidth, $height);
        $x = $this->drawVersusHeader($poule, $gameAmountConfig, $x, $y);

        // draw pointsrectangle
        $x = $this->drawHeaderCustom('punten', $x, $y, $this->pointsColumnWidth, $height);
        // draw rankrectangle
        $x = $this->drawHeaderCustom('plek', $x, $y, $this->rankColumnWidth, $height);

        return $y - $height;
    }

    abstract protected function drawPouleHeader(Poule $poule, GameAmountConfig $gameAmountConfig, float $y): float;

    abstract protected function drawVersusHeader(Poule $poule, GameAmountConfig $gameAmountConfig, float $x, float $y): float;

    abstract public function getPouleHeight(Poule $poule): float;

    abstract protected function drawVersusCell(Place $place, GameAmountConfig $gameAmountConfig, float $x, float $y): float;

    public function draw(Poule $poule, GameAmountConfig $gameAmountConfig, float $y): float
    {
        // draw first row
        $y = $this->drawPouleHeader($poule, $gameAmountConfig, $y);
        $competitionSport = $gameAmountConfig->getCompetitionSport();
        $pouleState = $poule->getState($competitionSport);

        $sportRankingItems = null;
        if ($pouleState === State::Finished) {
            $calculator = $this->getSportRankingCalculator($competitionSport);
            $sportRankingItems = $calculator->getItemsForPoule($poule);
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

            $x = $this->drawVersusCell($place, $gameAmountConfig, $x, $y);

            $sportRankingItem = $this->getSportRankingItem($place, $sportRankingItems);
            $points = $sportRankingItem !== null ? (string)$sportRankingItem->getPerformance()->getPoints() : '';

            $x = $this->drawCellCustom($points, $x, $y, $this->pointsColumnWidth, $height, Align::Right);

            $rank = $sportRankingItem !== null ? (string)$sportRankingItem->getUniqueRank() : '';
            $this->drawCellCustom($rank, $x, $y, $this->rankColumnWidth, $height, Align::Right);

            $y -= $height;
        }
        return $y - $height;
    }

    /**
     * @param Place $place
     * @param list<SportRoundRankingItem>|null $rankingItems
     * @return SportRoundRankingItem|null
     * @throws Exception
     */
    protected function getSportRankingItem(Place $place, array|null $rankingItems): SportRoundRankingItem|null
    {
        if ($rankingItems === null) {
            return null;
        }

        $arrFoundRankingItems = array_filter(
            $rankingItems,
            function (SportRoundRankingItem $rankingItem) use ($place): bool {
                return $rankingItem->getPerformance()->getPlace() === $place;
            }
        );
        $rankingItem = reset($arrFoundRankingItems);
        return $rankingItem === false ? null : $rankingItem;
    }
}
