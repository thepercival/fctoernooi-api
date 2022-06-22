<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page\GameNotes;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page\GameNotes;
use App\Export\Pdf\Page\GameNotes as GameNotesBase;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Together as TogetherGame;
use SportsHelpers\Against\Side as AgainstSide;

class Against extends GameNotesBase
{
    public function __construct(Document $document, mixed $param1, AgainstGame $gameOne, AgainstGame|null $gameTwo)
    {
        parent::__construct($document, $param1, $gameOne, $gameTwo);
    }

    protected function drawPlaces(AgainstGame|TogetherGame $game, float $x, float $y, float $width, float $height): void
    {
        if ($game instanceof TogetherGame) {
            return;
        }
        $structureNameService = $this->getStructureNameService();
        $homePlaces = $game->getSidePlaces(AgainstSide::Home);
        $home = $structureNameService->getPlacesFromName($homePlaces, false, count($homePlaces) === 1);
        $awayPlaces = $game->getSidePlaces(AgainstSide::Away);
        $away = $structureNameService->getPlacesFromName($awayPlaces, false, count($awayPlaces) === 1);
        $rectangle = new Rectangle(new HorizontalLine(new Point($x, $y), $width), $height);
        $this->drawCell($home . ' - ' . $away, $rectangle);
    }

    protected function drawGameRoundNumber(AgainstGame|TogetherGame $game, float $x, float $y, float $width, float $height): float
    {
        if ($game instanceof TogetherGame) {
            return $y;
        }
        $gameRoundNumber = (string)$game->getGameRoundNumber();
        $rectangle = new Rectangle(new HorizontalLine(new Point($x, $y), $width), $height);
        $this->drawCell($gameRoundNumber, $rectangle);
        return $y - $height;
    }

    protected function drawScore(AgainstGame|TogetherGame $game, float $y): void
    {
        if ($game instanceof TogetherGame) {
            return;
        }
        $structureNameService = $this->getStructureNameService();
        $roundNumber = $game->getRound()->getNumber();
        $planningConfig = $roundNumber->getValidPlanningConfig();
        $firstScoreConfig = $game->getScoreConfig();
        $margin = $this->parent->getConfig()->getMargin();
        $larger = 1.2;
        $height = $this->parent->getConfig()->getRowHeight() * $larger;
        $leftPartWidth = $this->getLeftPartWidth();
        $homeStart = $this->getStartDetailLabel();
        $homeWidth = $this->getDetailPartWidth();
        $sepStartX = $homeStart + $homeWidth;
        $awayStart = $this->getStartDetailValue();
        $dotsWidth = $this->getPartWidth();
        $unitStart = $awayStart + $dotsWidth + $margin;
        $unitWidth = $this->getPartWidth();
//        $detailValueWidth = $this->getDetailValueWidth($x);
//        $x2 = $this->getXSecondBorder() + ($margin * 0.5);

        // 2x font thuis - uit
        $this->setFont($this->helper->getTimesFont(), $this->parent->getConfig()->getFontHeight() * $larger);
        $rectangle = new Rectangle(new HorizontalLine(new Point(self::PAGEMARGIN, $y), $leftPartWidth), $height);
        $this->drawCell('wedstrijd', $rectangle, Align::Right);

        // COMPETITORS
        $home = $structureNameService->getPlacesFromName($game->getSidePlaces(AgainstSide::Home), true, true);
        $rectangle = new Rectangle(new HorizontalLine(new Point($homeStart, $y), $homeWidth), $height);
        $this->drawCell($home, $rectangle, Align::Right);
        $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), $height);
        $this->drawCell('-', $rectangle, Align::Center);
        $away = $structureNameService->getPlacesFromName($game->getSidePlaces(AgainstSide::Away), true, true);
        $rectangle = new Rectangle(new HorizontalLine(new Point($awayStart, $y), $dotsWidth), $height);
        $this->drawCell($away, $rectangle);
        $y -= 2 * $height;

        $this->setFont($this->helper->getTimesFont(), $this->parent->getConfig()->getFontHeight() * $larger);

        $calculateScoreConfig = $firstScoreConfig->getCalculate();

        $dots = '...............';
        $nrOfScoreLines = $this->getNrOfScoreLines($game->getRound(), $game->getCompetitionSport());

        // DOTS
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $yDelta = 0;
            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines; $gameUnitNr++) {
                $descr = $this->translationService->getScoreNameSingular($calculateScoreConfig) . ' ' . $gameUnitNr;
                $rectangle = new Rectangle(
                    new HorizontalLine(new Point(self::PAGEMARGIN, $y - $yDelta), $leftPartWidth), $height
                );
                $this->drawCell($descr, $rectangle, Align::Right);
                $rectangle = new Rectangle(
                    new HorizontalLine(new Point($homeStart, $y - $yDelta), $homeWidth), $height
                );
                $this->drawCell($dots, $rectangle, Align::Right);
                $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y - $yDelta), $margin), $height);
                $this->drawCell('-', $rectangle, Align::Center);
                $rectangle = new Rectangle(
                    new HorizontalLine(new Point($awayStart, $y - $yDelta), $dotsWidth), $height
                );
                $this->drawCell($dots, $rectangle);
                $yDelta += $height;
            }
        } else {
            $rectangle = new Rectangle(new HorizontalLine(new Point(self::PAGEMARGIN, $y), $leftPartWidth), $height);
            $this->drawCell('score', $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($homeStart, $y), $homeWidth), $height);
            $this->drawCell($dots, $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), $height);
            $this->drawCell('-', $rectangle, Align::Center);
            $rectangle = new Rectangle(new HorizontalLine(new Point($awayStart, $y), $dotsWidth), $height);
            $this->drawCell($dots, $rectangle);
        }

        // SCOREUNITS
        $descr = $this->getInputScoreConfigDescription($firstScoreConfig);
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $yDelta = 0;
            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines; $gameUnitNr++) {
                $rectangle = new Rectangle(
                    new HorizontalLine(new Point($unitStart, $y - $yDelta), $unitWidth), $height
                );
                $this->drawCell($descr, $rectangle, Align::Right);
                $yDelta += $height;
            }
            $y -= $yDelta;
        } else {
            $rectangle = new Rectangle(new HorizontalLine(new Point($unitStart, $y), $unitWidth), $height);
            $this->drawCell($descr, $rectangle, Align::Right);
        }

        $y -= $height; // extra lege regel

        if ($planningConfig->getExtension()) {
            $rectangle = new Rectangle(new HorizontalLine(new Point(self::PAGEMARGIN, $y), $leftPartWidth), $height);
            $this->drawCell('na verleng.', $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($homeStart, $y), $homeWidth), $height);
            $this->drawCell($dots, $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), $height);
            $this->drawCell('-', $rectangle, Align::Center);
            $rectangle = new Rectangle(new HorizontalLine(new Point($awayStart, $y), $dotsWidth), $height);
            $this->drawCell($dots, $rectangle);

            $name = $this->translationService->getScoreNamePlural($firstScoreConfig);
            $rectangle = new Rectangle(new HorizontalLine(new Point($unitStart, $y), $unitWidth), $height);
            $this->drawCell($name, $rectangle, Align::Right);
        }
    }
}
