<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page\PoulePivotTable;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document\PoulePivotTables as PoulePivotTablesDocument;
use App\Export\Pdf\Page\PoulePivotTables as PoulePivotTablesPage;
use Sports\Game\Against as AgainstGame;
use Sports\Game\State as GameState;
use Sports\Place;
use Sports\Planning\GameAmountConfig;
use Sports\Poule;
use SportsHelpers\Against\Side as AgainstSide;
use Zend_Pdf_Color_Html;

class Against extends PoulePivotTablesPage
{
    public function __construct(PoulePivotTablesDocument $document, mixed $param1)
    {
        parent::__construct($document, $param1);
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

    protected function drawPouleHeader(Poule $poule, GameAmountConfig $gameAmountConfig, float $y): float
    {
        $nrOfItems = $poule->getPlaces()->count();
        return parent::drawPouleHeaderHelper($poule, $gameAmountConfig, $y, $this->getVersusHeaderDegrees($nrOfItems));
    }

    protected function drawVersusHeader(Poule $poule, GameAmountConfig $gameAmountConfig, float $x, float $y): float
    {
        $nrOfPlaces = $poule->getPlaces()->count();
        $versusColumnWidth = $this->versusColumnsWidth / $nrOfPlaces;
        $degrees = $this->getVersusHeaderDegrees($nrOfPlaces);
        $height = $this->getVersusHeight($versusColumnWidth, $degrees);

        $nVersus = 0;
        foreach ($poule->getPlaces() as $place) {
            $placeName = $place->getPlaceNr() . ". ";
            $placeName .= $this->parent->getNameService()->getPlaceFromName($place, true);
            $this->setFont($this->parent->getFont(), $this->getPlaceFontHeight($placeName));
            $x = $this->drawHeaderCustom($placeName, $x, $y, $versusColumnWidth, $height, $degrees);
            $nVersus++;
        }
        $this->setFont($this->parent->getFont(), $this->parent->getFontHeight());
        return $x;
    }

    protected function drawVersusCell(Place $place, GameAmountConfig $gameAmountConfig, float $x, float $y): float
    {
        $poule = $place->getPoule();
        $games = $place->getAgainstGames($gameAmountConfig->getCompetitionSport());
        $columnWidth = $this->versusColumnsWidth / $poule->getPlaces()->count();

        // draw versus
        for ($placeNr = 1; $placeNr <= $poule->getPlaces()->count(); $placeNr++) {
            if ($poule->getPlace($placeNr) === $place) {
                $this->setFillColor(new Zend_Pdf_Color_Html('lightgrey'));
            }
            $score = $this->getScore($place, $poule->getPlace($placeNr), $games);
            $x = $this->drawCellCustom($score, $x, $y, $columnWidth, $this->rowHeight, Align::Center);
            if ($poule->getPlace($placeNr) === $place) {
                $this->setFillColor(new Zend_Pdf_Color_Html('white'));
            }
        }
        return $x;
    }


    /**
     * @param Place $homePlace
     * @param Place $awayPlace
     * @param list<AgainstGame> $placeGames
     * @return string
     */
    protected function getScore(Place $homePlace, Place $awayPlace, array $placeGames): string
    {
        $foundHomeGames = array_filter(
            $placeGames,
            function ($game) use ($homePlace, $awayPlace): bool {
                return $game->isParticipating($awayPlace, AgainstSide::Away) && $game->isParticipating(
                    $homePlace,
                    AgainstSide::Home
                );
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
                return $game->isParticipating($homePlace, AgainstSide::Away) && $game->isParticipating(
                    $awayPlace,
                    AgainstSide::Home
                );
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
        if ($game->getState() !== GameState::Finished) {
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

    // t/m 3 places 0g, t/m 8 places 45g, hoger 90g
    public function getPouleHeight(Poule $poule): float
    {
        $nrOfPlaces = $poule->getPlaces()->count();

        // header row
        $versusColumnWidth = $this->versusColumnsWidth / $nrOfPlaces;
        $degrees = $this->getVersusHeaderDegrees($nrOfPlaces);
        $height = $this->getVersusHeight($versusColumnWidth, $degrees);

        // places
        $height += $this->rowHeight * $nrOfPlaces;

        return $height;
    }
}
