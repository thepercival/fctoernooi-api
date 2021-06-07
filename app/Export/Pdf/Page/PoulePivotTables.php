<?php
declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Document;
use Sports\Ranking\Item\Round as RoundRankingItem;
use SportsHelpers\Against\Side as AgainstSide;
use App\Export\Pdf\Page as ToernooiPdfPage;
use Sports\NameService;
use Sports\Poule;
use Sports\Place;
use Sports\Game\Against as AgainstGame;
use Sports\State;
use Sports\Round\Number as RoundNumber;
use Sports\Ranking\Calculator\Round as RoundRankingCalculator;
use Sports\Score\Config\Service as ScoreConfigService;

class PoulePivotTables extends ToernooiPdfPage
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

    public function getPageMargin(): int
    {
        return 20;
    }

    public function getHeaderHeight(): int
    {
        return 0;
    }

    public function drawRoundNumberHeader(RoundNumber $roundNumber, float $nY): float
    {
        $fontHeightSubHeader = $this->getParent()->getFontHeightSubHeader();
        $this->setFont($this->getParent()->getFont(true), $this->getParent()->getFontHeightSubHeader());
        $nX = $this->getPageMargin();
        $displayWidth = $this->getDisplayWidth();
        $subHeader = $this->getParent()->getNameService()->getRoundNumberName($roundNumber);
        $this->drawCell($subHeader, $nX, $nY, $displayWidth, $fontHeightSubHeader, ToernooiPdfPage::ALIGNCENTER);
        $this->setFont($this->getParent()->getFont(), $this->getParent()->getFontHeight());
        return $nY - (2 * $fontHeightSubHeader);
    }

    // t/m 3 places 0g, t/m 8 places 45g, hoger 90g
    public function getPouleHeight(Poule $poule): float
    {
        $nrOfPlaces = $poule->getPlaces()->count();

        // header row
        $versusColumnWidth = $this->versusColumnsWidth / $nrOfPlaces;
        $degrees = $this->getDegrees($nrOfPlaces);
        $height = $this->getVersusHeight($versusColumnWidth, $degrees);

        // places
        $height += $this->rowHeight * $nrOfPlaces;

        return $height;
    }

    /*public function draw()
    {
        $tournament = $this->getParent()->getTournament();
        $firstRound = $this->getParent()->getStructure()->getRootRound();
        $this->setQual( $firstRound );
        $nY = $this->drawHeader( "draaitabel per poule" );
        $nY = $this->draw( $firstRound, $nY );
    }*/

    /*protected function setQual( Round $parentRound )
    {
        foreach ($parentRound->getChildRounds() as $childRound) {
            $qualifyService = new QualifyService($childRound);
            $qualifyService->setQualifyRules();
            $this->setQual( $childRound );
        }
    }*/

    public function drawPouleHeader(Poule $poule, $nY): float
    {
        $nrOfPlaces = $poule->getPlaces()->count();
        $versusColumnWidth = $this->versusColumnsWidth / $nrOfPlaces;
        $degrees = $this->getDegrees($nrOfPlaces);
        $height = $this->getVersusHeight($versusColumnWidth, $degrees);

        $nX = $this->getPageMargin();
        $nX = $this->drawCell(
            $this->getParent()->getNameService()->getPouleName($poule, true),
            $nX,
            $nY,
            $this->nameColumnWidth,
            $height,
            ToernooiPdfPage::ALIGNCENTER,
            'black'
        );

        $nVersus = 0;
        foreach ($poule->getPlaces() as $place) {
            $placeName = $place->getNumber() . ". " . $this->getParent()->getNameService()->getPlaceFromName(
                    $place,
                    true
                );
            $this->setFont($this->getParent()->getFont(), $this->getPlaceFontHeight($placeName));
            $nX = $this->drawCell(
                $placeName,
                $nX,
                $nY,
                $versusColumnWidth,
                $height,
                ToernooiPdfPage::ALIGNCENTER,
                'black',
                $degrees
            );
            $nVersus++;
        }
        $this->setFont($this->getParent()->getFont(), $this->getParent()->getFontHeight());
        // draw pointsrectangle
        $nX = $this->drawCell(
            "punten",
            $nX,
            $nY,
            $this->pointsColumnWidth,
            $height,
            ToernooiPdfPage::ALIGNCENTER,
            'black'
        );

        // draw rankrectangle
        $this->drawCell("plek", $nX, $nY, $this->rankColumnWidth, $height, ToernooiPdfPage::ALIGNCENTER, 'black');

        return $nY - $height;
    }

    public function draw(Poule $poule, $nY): float
    {
        // draw first row
        $nY = $this->drawPouleHeader($poule, $nY);

        $pouleState = $poule->getState();
        $competition = $this->getParent()->getTournament()->getCompetition();
        /** @var list<RoundRankingItem>|null $rankingItems */
        $rankingItems = null;
        if ($pouleState === State::Finished) {
            $rankingService = new RoundRankingCalculator();
            $rankingItems = $rankingService->getItemsForPoule($poule);
        }

        $nRowHeight = $this->rowHeight;
        $nrOfPlaces = $poule->getPlaces()->count();
        $versusColumnWidth = $this->versusColumnsWidth / $nrOfPlaces;

        foreach ($poule->getPlaces() as $place) {
            $nX = $this->getPageMargin();
            // placename
            {
                $placeName = $place->getNumber() . ". " . $this->getParent()->getNameService()->getPlaceFromName(
                        $place,
                        true
                    );
                $this->setFont($this->getParent()->getFont(), $this->getPlaceFontHeight($placeName));
                $nX = $this->drawCell(
                    $placeName,
                    $nX,
                    $nY,
                    $this->nameColumnWidth,
                    $nRowHeight,
                    ToernooiPdfPage::ALIGNLEFT,
                    'black'
                );
                $this->setFont($this->getParent()->getFont(), $this->getParent()->getFontHeight());
            }

            $placeGames = $place->getGames();
            // draw versus
            for ($placeNr = 1; $placeNr <= $nrOfPlaces; $placeNr++) {
                if ($poule->getPlace($placeNr) === $place) {
                    $this->setFillColor(new \Zend_Pdf_Color_Html("lightgrey"));
                }
                $score = '';
                if ($pouleState !== State::Created) {
                    $score = $this->getScore($place, $poule->getPlace($placeNr), $placeGames);
                }
                $nX = $this->drawCell(
                    $score,
                    $nX,
                    $nY,
                    $versusColumnWidth,
                    $nRowHeight,
                    ToernooiPdfPage::ALIGNCENTER,
                    'black'
                );
                if ($poule->getPlace($placeNr) === $place) {
                    $this->setFillColor(new \Zend_Pdf_Color_Html("white"));
                }
            }
            /** @var RoundRankingItem|null $rankingItem */
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
            $points = '';
            if ($rankingItem !== null) {
                $points = '' . $rankingItem->getUnranked()->getPoints();
            }
            $nX = $this->drawCell(
                $points,
                $nX,
                $nY,
                $this->pointsColumnWidth,
                $nRowHeight,
                ToernooiPdfPage::ALIGNRIGHT,
                'black'
            );

            // draw rankrectangle
            $rank = '';
            if ($rankingItem !== null) {
                $rank = '' . $rankingItem->getUniqueRank();
            }
            $this->drawCell($rank, $nX, $nY, $this->rankColumnWidth, $nRowHeight, ToernooiPdfPage::ALIGNRIGHT, 'black');

            $nY -= $nRowHeight;
        }
        return $nY - $nRowHeight;
    }

    protected function getScore(Place $homePlace, Place $awayPlace, array $placeGames): string
    {
        $foundHomeGames = array_filter(
            $placeGames,
            function ($game) use ($homePlace, $awayPlace): bool {
                return $game->isParticipating($awayPlace, AgainstSide::AWAY) && $game->isParticipating($homePlace, AgainstSide::HOME);
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
                return $game->isParticipating($homePlace, AgainstSide::AWAY) && $game->isParticipating($awayPlace, AgainstSide::HOME);
            }
        );
        if (count($foundAwayGames) !== 1) {
            return '';
        }
        return $this->getGameScore(reset($foundAwayGames), true);
    }

    protected function getGameScore(AgainstGame $game, bool $reverse): string
    {
        $score = ' - ';
        if ($game->getState() !== State::Finished) {
            return $score;
        }
        $finalScore = $this->scoreConfigService->getFinalAgainstScore($game);
        if ($finalScore === null) {
            return $score;
        }
        if ($reverse === true) {
            return $finalScore->getAway() . $score . $finalScore->getHome();
        }
        return $finalScore->getHome() . $score . $finalScore->getAway();
    }

    public function getDegrees(int $nrOfPlaces): int
    {
        if ($nrOfPlaces <= 3) {
            return 0;
        }
        if ($nrOfPlaces >= 6) {
            return 90;
        }
        return 45;
    }

    public function getVersusHeight(float $versusColumnWidth, int $degrees): float
    {
        if ($degrees === 0) {
            return $this->rowHeight;
        }
        if ($degrees === 90) {
            return $versusColumnWidth * 2;
        }
        return (tan(deg2rad($degrees)) * $versusColumnWidth);
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
