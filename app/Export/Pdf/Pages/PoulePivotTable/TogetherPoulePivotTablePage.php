<?php

declare(strict_types=1);

namespace App\Export\Pdf\Pages\PoulePivotTable;

use App\Export\Pdf\Align;
use App\Export\Pdf\Documents\PoulePivotTablesDocument as PoulePivotTablesDocument;
use App\Export\Pdf\Pages\PoulePivotTablesPage as PoulePivotTablesPage;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Game\Place\Together as TogetherGamePlace;
use Sports\Game\State as GameState;
use Sports\Place;
use Sports\Planning\GameAmountConfig;
use Sports\Poule;

class TogetherPoulePivotTablePage extends PoulePivotTablesPage
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

    // t/m 3 places 0g, t/m 8 places 45g, hoger 90g
    public function getPouleHeight(Poule $poule): float
    {
        $height = $this->parent->getConfig()->getRowHeight();
        // places
        $height += $this->parent->getConfig()->getRowHeight() * $poule->getPlaces()->count();

        return $height;
    }

    protected function drawPouleHeader(Poule $poule, GameAmountConfig $gameAmountConfig, float $y): float
    {
        return parent::drawPouleHeaderHelper($poule, $gameAmountConfig, $y);
    }

    protected function drawVersusHeader(Poule $poule, GameAmountConfig $gameAmountConfig, float $x, float $y): float
    {
        $versusColumnWidth = $this->versusColumnsWidth / $gameAmountConfig->getAmount();
        $height = $this->getVersusHeight($versusColumnWidth);

        for ($gameRoundNumber = 1 ; $gameRoundNumber <= $gameAmountConfig->getAmount() ; $gameRoundNumber++) {
            $x = $this->drawHeaderCustom((string)$gameRoundNumber, $x, $y, $versusColumnWidth, $height);
        }
        return $x;
    }

    protected function drawVersusCell(Place $place, GameAmountConfig $gameAmountConfig, float $x, float $y): float
    {
        $columnWidth = $this->versusColumnsWidth / $gameAmountConfig->getAmount();
        for ($gameRoundNumber = 1 ; $gameRoundNumber <= $gameAmountConfig->getAmount() ; $gameRoundNumber++) {
            $score = $this->getScore($place, $gameAmountConfig->getCompetitionSport(), $gameRoundNumber);
            $x = $this->drawCellCustom(
                $score,
                $x,
                $y,
                $columnWidth,
                $this->parent->getConfig()->getRowHeight(),
                Align::Center
            );
        }
        return $x;
    }

    protected function getScore(Place $place, CompetitionSport $competitionSport, int $gameRoundNumber): string
    {
        $gamePlace = $this->getGamePlace($place, $competitionSport, $gameRoundNumber);
        if ($gamePlace === null) {
            return '';
        }
        if ($gamePlace->getGame()->getState() !== GameState::Finished) {
            return '';
        }
        return (string)$this->scoreConfigService->getFinalTogetherScore($gamePlace);
    }

    protected function getGamePlace(Place $place, CompetitionSport $competitionSport, int $gameRoundNumber): TogetherGamePlace|null
    {
        $foundGames = array_filter(
            $place->getTogetherGamePlaces($competitionSport),
            function (TogetherGamePlace $gamePlace) use ($gameRoundNumber): bool {
                return $gamePlace->getGameRoundNumber() === $gameRoundNumber;
            }
        );
        $foundGame = reset($foundGames);
        return $foundGame === false ? null : $foundGame;
    }
}
