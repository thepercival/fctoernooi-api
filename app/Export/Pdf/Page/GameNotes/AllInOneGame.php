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

class AllInOneGame extends GameNotesBase
{
    public const ONE_PAGE_MAX_NROFSCORELINES = 6;

    public function __construct(Document $document, mixed $param1, TogetherGame $gameOne, TogetherGame|null $gameTwo)
    {
        parent::__construct($document, $param1, $gameOne, $gameTwo);
    }

    protected function drawPlaces(AgainstGame|TogetherGame $game, float $x, float $y, float $width, float $height): void
    {
        if ($game instanceof AgainstGame) {
            return;
        }
        $structureNameService = $this->getStructureNameService();
        $places = array_values($game->getPlaces()->toArray());
        $description = $structureNameService->getPlacesFromName($places, false, count($places) <= 3);
        $rectangle = new Rectangle(new HorizontalLine(new Point($x, $y), $width), $height);
        $this->drawCell($description, $rectangle);
    }

    protected function drawGameRoundNumber(AgainstGame|TogetherGame $game, float $x, float $y, float $width, float $height): float
    {
        if ($game instanceof AgainstGame) {
            return $y;
        }
        $gamePlace = $game->getPlaces()->first();
        $roundNumber = $gamePlace === false ? '' : (string)$gamePlace->getGameRoundNumber();
        $rectangle = new Rectangle(new HorizontalLine(new Point($x, $y), $width), $height);
        $this->drawCell($roundNumber, $rectangle);
        return $y - $height;
    }

    protected function getPlaceWidth(TogetherGame $game): float
    {
        $margin = $this->parent->getConfig()->getMargin();
        $placesWidth = $this->getDetailPartWidth() + $margin + $this->getDetailPartWidth();
        $placesWidth -= ($margin + $this->getPartWidth()); // unit(right side)
        $placesWidth -= ($game->getPlaces()->count() - 1) * $margin;
        return $placesWidth / $game->getPlaces()->count();
    }

    protected function drawScore(AgainstGame|TogetherGame $game, float $y): void
    {
        if ($game instanceof AgainstGame) {
            return;
        }
        $structureNameService = $this->getStructureNameService();
        $firstScoreConfig = $game->getScoreConfig();
        $calculateScoreConfig = $firstScoreConfig->getCalculate();
        $nrOfScoreLines = $this->getNrOfScoreLines($game->getRound(), $game->getCompetitionSport());
        $fontSize = $this->parent->getConfig()->getFontHeight();
        $larger = 1.2;
        $largerFontSize = $fontSize * $larger;
        $height = $this->parent->getConfig()->getRowHeight() * $larger;
        $leftPartWidth = $this->getLeftPartWidth();
        $placesStart = self::PAGEMARGIN;
        $unitWidth = $this->getPartWidth();
        $unitStart = $this->getStartDetailLabel();

        // 2x font thuis - uit
        $this->setFont($this->helper->getTimesFont(), $largerFontSize);
        $rectangle = new Rectangle(new HorizontalLine(new Point(self::PAGEMARGIN, $y), $leftPartWidth), $height);
        $this->drawCell('wedstrijd', $rectangle, Align::Right);

        // SCOREUNITS
        $descr = $this->getInputScoreConfigDescription($firstScoreConfig);
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $unitX = $unitStart;
            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines; $gameUnitNr++) {
                $rectangle = new Rectangle(new HorizontalLine(new Point($unitX, $y), $unitWidth), $height);
                $this->drawCell($descr, $rectangle, Align::Right);
                $unitX += $unitWidth + $this->parent->getConfig()->getMargin();
            }
        } else {
            $rectangle = new Rectangle(new HorizontalLine(new Point($unitStart, $y), $unitWidth), $height);
            $this->drawCell($descr, $rectangle, Align::Right);
        }
        $y -= 2 * $height;

        // COMPETITORS
        $this->setFont($this->helper->getTimesFont(), $fontSize);
        $dots = '...............';
        foreach ($game->getPlaces() as $gamePlace) {
            $name = $structureNameService->getPlaceFromName($gamePlace->getPlace(), true, true);
            $rectangle = new Rectangle(new HorizontalLine(new Point($placesStart, $y), $leftPartWidth), $height);
            $this->drawCell($name, $rectangle, Align::Right);
            // $x += $rectangle->getRight()->getX() + $this->config->getMargin();

            // DOTS
            if ($firstScoreConfig !== $calculateScoreConfig) {
//                $yDelta = 0;
//
//                for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines; $gameUnitNr++) {
//                    $descr = $this->translationService->getScoreNameSingular($calculateScoreConfig) . ' ' . $gameUnitNr;
//                    $this->drawCell($descr, self::PAGEMARGIN, $y - $yDelta, $leftPartWidth, $height, Align::Right);
//
//                    $placesX = $placesStart;
//                    foreach ($game->getPlaces() as $gamePlace) {
//                        $placesX = $this->drawCell($dots, $placesX, $y - $yDelta, $placeWidth, $height);
//                        $placesX += GameNotes::Margin;
//                    }
//
//                    $yDelta += $height;
//                }
                $unitX = $unitStart;
                for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines; $gameUnitNr++) {
                    $rectangle = new Rectangle(new HorizontalLine(new Point($unitX, $y), $unitWidth), $height);
                    $this->drawCell($dots, $rectangle, Align::Right);
                    $unitX += $unitWidth + $this->parent->getConfig()->getMargin();
                }
            } else {
//                $this->drawCell('score', self::PAGEMARGIN, $y, $leftPartWidth, $height, Align::Right);
//                $placesX = $placesStart;
//                // loop door de scoreunits heen
                $rectangle = new Rectangle(new HorizontalLine(new Point($unitStart, $y), $unitWidth), $height);
                $this->drawCell($dots, $rectangle, Align::Right);
            }
            $y -= $height;
        }

//
//        if ($planningConfig->getExtension()) {
//            $this->drawCell('na verleng.', self::PAGEMARGIN, $y, $leftPartWidth, $height, Align::Right);
//            $placesX = $placesStart;
//            foreach ($game->getPlaces() as $gamePlace) {
//                $placesX = $this->drawCell($dots, $placesX, $y, $placeWidth, $height);
//                $placesX += GameNotes::Margin;
//            }
//
//            $name = $this->translationService->getScoreNamePlural($firstScoreConfig);
//            $this->drawCell($name, $unitStart, $y, $unitWidth, $height, Align::Right);
//        }
    }
}
