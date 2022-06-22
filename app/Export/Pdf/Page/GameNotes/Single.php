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
use Sports\Game\Place\Together as TogetherGamePlace;
use Sports\Game\Together as TogetherGame;

class Single extends GameNotesBase
{
    public function __construct(
        Document $document,
        mixed $param1,
        TogetherGame $gameOne,
        TogetherGame|null $gameTwo)
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
        $grs = array_map(fn (TogetherGamePlace $gp): string => (string)$gp->getGameRoundNumber(), $game->getPlaces()->toArray());
        $rectangle = new Rectangle(new HorizontalLine(new Point($x, $y), $width), $height);
        $this->drawCell(implode(' & ', $grs), $rectangle);
        return $y - $height;
    }

    protected function getPlaceWidth(TogetherGame $game): float
    {
        $margin = $this->parent->getConfig()->getMargin();
        $placesWidth = $this->getDetailPartWidth() + $margin + $this->getDetailPartWidth();
        $placesWidth -=  ($margin + $this->getPartWidth()); // unit(right side)
        $placesWidth -= ($game->getPlaces()->count() - 1) * $margin;
        return $placesWidth / $game->getPlaces()->count();
    }

    protected function drawScore(AgainstGame|TogetherGame $game, float $y): void
    {
        if ($game instanceof AgainstGame) {
            return;
        }
        $margin = $this->parent->getConfig()->getMargin();
        $structureNameService = $this->getStructureNameService();
        $roundNumber = $game->getRound()->getNumber();
        $planningConfig = $roundNumber->getValidPlanningConfig();
        $firstScoreConfig = $game->getScoreConfig();
        $fontSize = $this->parent->getConfig()->getFontHeight();
        $larger = 1.2;
        $largerFontSize = $this->parent->getConfig()->getFontHeight() * $larger;
        $height = $this->parent->getConfig()->getRowHeight() * $larger;
        $leftPartWidth = $this->getLeftPartWidth();
        $placesStart = $this->getStartDetailLabel();
        $placeWidth = $this->getPlaceWidth($game);
        $unitWidth = $this->getPartWidth();
        $unitStart = $placesStart;
        foreach ($game->getPlaces() as $gamePlace) {
            $unitStart += $placeWidth + $this->parent->getConfig()->getMargin();
        }

        // 2x font thuis - uit
        $this->setFont($this->helper->getTimesFont(), $largerFontSize);
        $rectangle = new Rectangle(new HorizontalLine(new Point(self::PAGEMARGIN, $y), $leftPartWidth), $height);
        $this->drawCell('wedstrijd', $rectangle, Align::Right);

        // COMPETITORS
        $competitorFontSize = $game->getPlaces()->count() > 3 ? $fontSize : $largerFontSize;
        $this->setFont($this->helper->getTimesFont(), $competitorFontSize);
        $placesX = $placesStart;
        foreach ($game->getPlaces() as $gamePlace) {
            $name = $structureNameService->getPlaceFromName($gamePlace->getPlace(), true, true);
            $rectangle = new Rectangle(new HorizontalLine(new Point($placesX, $y), $placeWidth + $margin), $height);
            $this->drawCell($name, $rectangle);
            $placesX = $rectangle->getRight()->getX() + $margin;
        }
        $y -= 2 * $height;

        $this->setFont($this->helper->getTimesFont(), $largerFontSize);

        $calculateScoreConfig = $firstScoreConfig->getCalculate();
        $nrOfScoreLines = $this->getNrOfScoreLines($game->getRound(), $game->getCompetitionSport());
        $dots = '...............';

        // DOTS
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $yDelta = 0;

            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines; $gameUnitNr++) {
                $descr = $this->translationService->getScoreNameSingular($calculateScoreConfig) . ' ' . $gameUnitNr;
                $rectangle = new Rectangle(new HorizontalLine(new Point(self::PAGEMARGIN, $y - $yDelta), $leftPartWidth), $height);
                $this->drawCell($descr, $rectangle, Align::Right);

                $placesX = $placesStart;
                foreach ($game->getPlaces() as $gamePlace) {
                    $rectangle = new Rectangle(new HorizontalLine(new Point($placesX, $y - $yDelta), $placeWidth), $height);
                    $this->drawCell($dots, $rectangle);
                    $placesX = $rectangle->getRight()->getX() + $margin;
                }
                $yDelta += $height;
            }
        } else {
            $rectangle = new Rectangle(new HorizontalLine(new Point(self::PAGEMARGIN, $y), $leftPartWidth), $height);
            $this->drawCell('score', $rectangle, Align::Right);
            $placesX = $placesStart;
            foreach ($game->getPlaces() as $gamePlace) {
                $rectangle = new Rectangle(new HorizontalLine(new Point($placesX, $y), $placeWidth), $height);
                $this->drawCell($dots, $rectangle, Align::Left);
                $placesX = $rectangle->getRight()->getX() + $margin;
            }
        }

        // SCOREUNITS
        $descr = $this->getInputScoreConfigDescription($firstScoreConfig);
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $yDelta = 0;
            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines; $gameUnitNr++) {
                $rectangle = new Rectangle(new HorizontalLine(new Point($unitStart, $y - $yDelta), $unitWidth), $height);
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
            $placesX = $placesStart;
            foreach ($game->getPlaces() as $gamePlace) {
                $rectangle = new Rectangle(new HorizontalLine(new Point($placesX, $y), $placeWidth), $height);
                $this->drawCell($dots, $rectangle);
                $placesX = $rectangle->getRight()->getX() + $margin;
            }

            $name = $this->translationService->getScoreNamePlural($firstScoreConfig);
            $rectangle = new Rectangle(new HorizontalLine(new Point($unitStart, $y), $unitWidth), $height);
            $this->drawCell($name, $rectangle, Align::Right);
        }
    }
}
