<?php
declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document;
use Sports\Planning\GameAmountConfig;
use Sports\Ranking\Item\Round as RoundRankingItem;
use SportsHelpers\Against\Side as AgainstSide;
use App\Export\Pdf\Page as ToernooiPdfPage;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Poule;
use Sports\Place;
use Sports\Game\Against as AgainstGame;
use Sports\State;
use Sports\Round\Number as RoundNumber;
use Sports\Ranking\Calculator\Round as RoundRankingCalculator;
use Sports\Score\Config\Service as ScoreConfigService;
use Zend_Pdf_Color_Html;

abstract class PoulePivotTables extends ToernooiPdfPage
{
    protected ScoreConfigService $scoreConfigService;
    protected float $nameColumnWidth;
    protected float $pointsColumnWidth;
    protected float $rankColumnWidth;
    protected float $versusColumnsWidth;
    // protected $maxPoulesPerLine;
    // protected $placeWidthStructure;
    // protected $pouleMarginStructure;
    protected int $rowHeight;
    /**
     * @var array<string, int>
     */
    protected array $fontSizeMap;

    public function __construct(Document $document, mixed $param1)
    {
        parent::__construct($document, $param1);
        $this->setLineWidth(0.5);
        $this->nameColumnWidth = $this->getDisplayWidth() * 0.25;
        $this->versusColumnsWidth = $this->getDisplayWidth() * 0.62;
        $this->pointsColumnWidth = $this->getDisplayWidth() * 0.08;
        $this->rankColumnWidth = $this->getDisplayWidth() * 0.05;
        $this->scoreConfigService = new ScoreConfigService();
        $this->fontSizeMap = [];
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

    public function drawRoundNumberHeader(RoundNumber $roundNumber, float $y): float
    {
        $fontHeightSubHeader = $this->getParent()->getFontHeightSubHeader();
        $this->setFont($this->getParent()->getFont(true), $this->getParent()->getFontHeightSubHeader());
        $x = $this->getPageMargin();
        $displayWidth = $this->getDisplayWidth();
        $subHeader = $this->getParent()->getNameService()->getRoundNumberName($roundNumber);
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

    public function getVersusHeight(float $versusColumnWidth, int $degrees = 0): float
    {
        if ($degrees === 0) {
            return $this->rowHeight;
        }
        if ($degrees === 90) {
            return $versusColumnWidth * 2;
        }
        return (tan(deg2rad($degrees)) * $versusColumnWidth);
    }

    public function draw(Poule $poule, GameAmountConfig $gameAmountConfig, float $y): float
    {
        // draw first row
        $y = $this->drawPouleHeader($poule, $gameAmountConfig, $y);

        $pouleState = $poule->getState();
        $competitionSport = $gameAmountConfig->getCompetitionSport();
        $rankingItems = null;
        if ($pouleState === State::Finished) {
            $rankingItems = (new RoundRankingCalculator())->getItemsForPoule($poule);
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

            $rankingItem = $this->getRankingItem($place, $rankingItems);
            $points = $this->getPoints($rankingItem, $competitionSport);
            $x = $this->drawCellCustom($points, $x, $y, $this->pointsColumnWidth, $height, Align::Right);

            $rank = $this->getRank($rankingItem, $competitionSport);
            $this->drawCellCustom($rank, $x, $y, $this->rankColumnWidth, $height, Align::Right);

            $y -= $height;
        }
        return $y - $height;
    }

    protected function drawHeaderCustom(string $text, float $x, float $y, float $width, float $height, int $degrees = 0): float
    {
        return $this->drawCell($text, $x, $y, $width, $height, Align::Center, 'black', $degrees);
    }

    protected function drawCellCustom(string $text, float $x, float $y, float $width, float $height, int $align): float
    {
        return $this->drawCell($text, $x, $y, $width, $height, $align, 'black');
    }

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

    /**
     * @param RoundRankingItem $rankingItems
     * @param CompetitionSport $competitionSport
     * @return string
     * @throws \Exception
     */
    protected function getPoints(RoundRankingItem|null $rankingItem, CompetitionSport $competitionSport): string
    {
        if ($rankingItem === null) {
            return '';
        }
        $sportRankingItem = $rankingItem->getSportItem($competitionSport);
        return (string)$sportRankingItem->getPerformance()->getPoints();
    }

    /**
     * @param RoundRankingItem $rankingItems
     * @param CompetitionSport $competitionSport
     * @return string
     * @throws \Exception
     */
    protected function getRank(RoundRankingItem|null $rankingItem, CompetitionSport $competitionSport): string
    {
        if ($rankingItem === null) {
            return '';
        }
        $sportRankingItem = $rankingItem->getSportItem($competitionSport);
        return (string)$sportRankingItem->getUniqueRank();
    }

    protected function getPlaceFontHeight(string $placeName): int
    {
        if (array_key_exists($placeName, $this->fontSizeMap)) {
            return $this->fontSizeMap[$placeName];
        }
        $fontHeight = $this->getParent()->getFontHeight();
        if ($this->getTextWidth($placeName) > $this->nameColumnWidth) {
            $fontHeight -= 2;
        }
        $this->fontSizeMap[$placeName] = $fontHeight;
        return $fontHeight;
    }
}
