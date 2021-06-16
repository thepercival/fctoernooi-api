<?php
declare(strict_types=1);

namespace App\Export\Pdf\Page\PoulePivotTable;

use App\Export\Pdf\Document;
use App\Export\Pdf\Align;
use Sports\Place;
use Sports\Planning\GameAmountConfig;
use Sports\Ranking\Item\Round as RoundRankingItem;
use SportsHelpers\Against\Side as AgainstSide;
use App\Export\Pdf\Page\PoulePivotTables as PoulePivotTablesPage;
use Sports\NameService;
use Sports\Poule;
use Sports\Game\Place\Together as TogetherGamePlace;
use Sports\Game\Against as AgainstGame;
use Sports\State;
use Sports\Round\Number as RoundNumber;
use Sports\Ranking\Calculator\Round as RoundRankingCalculator;
use Sports\Score\Config\Service as ScoreConfigService;

class Together extends PoulePivotTablesPage
{
    public function __construct(Document $document, mixed $param1)
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
        $height = $this->rowHeight;
        // places
        $height += $this->rowHeight * $poule->getPlaces()->count();

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
            $score = $this->getScore($place, $gameRoundNumber);
            $x = $this->drawCellCustom($score, $x, $y, $columnWidth, $this->rowHeight, Align::Center);
        }
        return $x;
    }

    protected function getScore(Place $place, int $gameRoundNumber): string
    {
        $gamePlace = $this->getGamePlace($place, $gameRoundNumber);
        if ($gamePlace === null) {
            return '';
        }
        if ($gamePlace->getGame()->getState() !== State::Finished) {
            return '';
        }
        return (string)$this->scoreConfigService->getFinalTogetherScore($gamePlace);
    }

    protected function getGamePlace(Place $place, int $gameRoundNumber): TogetherGamePlace|null
    {
        $foundGames = array_filter(
            $place->getTogetherGamePlaces(),
            function (TogetherGamePlace $gamePlace) use ($gameRoundNumber): bool {
                return $gamePlace->getGameRoundNumber() === $gameRoundNumber;
            }
        );
        $foundGame = reset($foundGames);
        return $foundGame === false ? null : $foundGame;
    }
}