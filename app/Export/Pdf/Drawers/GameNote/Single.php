<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\GameNote;

use App\Export\Pdf\Align;
use App\Export\Pdf\Drawers\GameNote as GameNotesDrawer;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page;
use App\Export\Pdf\Page\GameNotes as GameNotesPage;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Place\Together as TogetherGamePlace;
use Sports\Game\Together as TogetherGame;

class Single extends GameNotesDrawer
{
    protected function drawPlaces(GameNotesPage $page, AgainstGame|TogetherGame $game, Rectangle $rectangle): void
    {
        if ($game instanceof AgainstGame) {
            return;
        }
        $structureNameService = $page->getParent()->getStructureNameService();
        $places = array_values($game->getPlaces()->toArray());
        $description = $structureNameService->getPlacesFromName($places, false, count($places) <= 3);
        // $rectangle = new Rectangle(new HorizontalLine(new Point($x, $y), $width), -$height);
        $page->drawCell($description, $rectangle);
    }

    protected function drawGameRoundNumber(
        GameNotesPage $page,
        AgainstGame|TogetherGame $game,
        Rectangle $rectangle
    ): HorizontalLine {
        if ($game instanceof AgainstGame) {
            return $rectangle->getTop();
        }
        $grs = array_map(
            fn(TogetherGamePlace $gp): string => (string)$gp->getGameRoundNumber(),
            $game->getPlaces()->toArray()
        );
        // $rectangle = new Rectangle(new HorizontalLine(new Point($x, $y), $width), -$height);
        $page->drawCell(implode(' & ', $grs), $rectangle);
        return $rectangle->getBottom();
    }

    protected function getPlaceWidth(TogetherGame $game, HorizontalLine $horLine): float
    {
        $margin = $this->config->getMargin();
        $placesWidth = $this->getDetailPartWidth($horLine) + $margin + $this->getDetailPartWidth($horLine);
        $placesWidth -= ($margin + $this->getPartWidth($horLine)); // unit(right side)
        $placesWidth -= ($game->getPlaces()->count() - 1) * $margin;
        return $placesWidth / $game->getPlaces()->count();
    }

    protected function drawScore(GameNotesPage $page, AgainstGame|TogetherGame $game, HorizontalLine $top): void
    {
        if ($game instanceof AgainstGame) {
            return;
        }
        $margin = $this->config->getMargin();
        $structureNameService = $page->getParent()->getStructureNameService();
        $roundNumber = $game->getRound()->getNumber();
        $planningConfig = $roundNumber->getValidPlanningConfig();
        $firstScoreConfig = $game->getScoreConfig();
        $fontSize = $this->config->getFontHeight();
        $larger = 1.2;
        $largerFontSize = $this->config->getFontHeight() * $larger;
        $height = $this->config->getRowHeight() * $larger;
        $leftPartWidth = $this->getLeftPartWidth($top);
        $placesStart = $this->getStartDetailLabel($top);
        $placeWidth = $this->getPlaceWidth($game, $top);
        $unitWidth = $this->getPartWidth($top);
        $unitStart = $placesStart;
        foreach ($game->getPlaces() as $gamePlace) {
            $unitStart += $placeWidth + $this->config->getMargin();
        }

        $y = $top->getY();
        // 2x font thuis - uit
        $page->setFont($this->helper->getTimesFont(), $largerFontSize);
        $rectangle = new Rectangle(new HorizontalLine(new Point(Page::PAGEMARGIN, $y), $leftPartWidth), -$height);
        $page->drawCell('wedstrijd', $rectangle, Align::Right);

        // COMPETITORS
        $competitorFontSize = $game->getPlaces()->count() > 3 ? $fontSize : $largerFontSize;
        $page->setFont($this->helper->getTimesFont(), $competitorFontSize);
        $placesX = $placesStart;
        foreach ($game->getPlaces() as $gamePlace) {
            $name = $structureNameService->getPlaceFromName($gamePlace->getPlace(), true, true);
            $rectangle = new Rectangle(new HorizontalLine(new Point($placesX, $y), $placeWidth + $margin), -$height);
            $page->drawCell($name, $rectangle);
            $placesX = $rectangle->getRight()->getX() + $margin;
        }
        $y -= 2 * $height;

        $page->setFont($this->helper->getTimesFont(), $largerFontSize);

        $calculateScoreConfig = $firstScoreConfig->getCalculate();
        $nrOfScoreLines = $this->getNrOfScoreLines($game->getRound(), $game->getCompetitionSport());
        $dots = '...............';

        // DOTS
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $yDelta = 0;

            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines; $gameUnitNr++) {
                $descr = $this->translationService->getScoreNameSingular($calculateScoreConfig) . ' ' . $gameUnitNr;
                $rectangle = new Rectangle(
                    new HorizontalLine(new Point(Page::PAGEMARGIN, $y - $yDelta), $leftPartWidth), -$height
                );
                $page->drawCell($descr, $rectangle, Align::Right);

                $placesX = $placesStart;
                foreach ($game->getPlaces() as $gamePlace) {
                    $rectangle = new Rectangle(
                        new HorizontalLine(new Point($placesX, $y - $yDelta), $placeWidth),
                        -$height
                    );
                    $page->drawCell($dots, $rectangle);
                    $placesX = $rectangle->getRight()->getX() + $margin;
                }
                $yDelta += $height;
            }
        } else {
            $rectangle = new Rectangle(new HorizontalLine(new Point(Page::PAGEMARGIN, $y), $leftPartWidth), -$height);
            $page->drawCell('score', $rectangle, Align::Right);
            $placesX = $placesStart;
            foreach ($game->getPlaces() as $gamePlace) {
                $rectangle = new Rectangle(new HorizontalLine(new Point($placesX, $y), $placeWidth), -$height);
                $page->drawCell($dots, $rectangle, Align::Left);
                $placesX = $rectangle->getRight()->getX() + $margin;
            }
        }

        // SCOREUNITS
        $descr = $this->getInputScoreConfigDescription($firstScoreConfig);
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $yDelta = 0;
            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines; $gameUnitNr++) {
                $rectangle = new Rectangle(
                    new HorizontalLine(new Point($unitStart, $y - $yDelta), $unitWidth), -$height
                );
                $page->drawCell($descr, $rectangle, Align::Right);
                $yDelta += $height;
            }
            $y -= $yDelta;
        } else {
            $rectangle = new Rectangle(new HorizontalLine(new Point($unitStart, $y), $unitWidth), -$height);
            $page->drawCell($descr, $rectangle, Align::Right);
        }

        $y -= $height; // extra lege regel

        if ($planningConfig->getExtension()) {
            $rectangle = new Rectangle(new HorizontalLine(new Point(Page::PAGEMARGIN, $y), $leftPartWidth), $height);
            $page->drawCell('na verleng.', $rectangle, Align::Right);
            $placesX = $placesStart;
            foreach ($game->getPlaces() as $gamePlace) {
                $rectangle = new Rectangle(new HorizontalLine(new Point($placesX, $y), $placeWidth), -$height);
                $page->drawCell($dots, $rectangle);
                $placesX = $rectangle->getRight()->getX() + $margin;
            }

            $name = $this->translationService->getScoreNamePlural($firstScoreConfig);
            $rectangle = new Rectangle(new HorizontalLine(new Point($unitStart, $y), $unitWidth), -$height);
            $page->drawCell($name, $rectangle, Align::Right);
        }
    }
}
