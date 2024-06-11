<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\GameNote;

use App\Export\Pdf\Align;
use App\Export\Pdf\Drawers\GameNote as GameNotesDrawer;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Pages\GameNotesPage as GameNotesPage;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Together as TogetherGame;

class AllInOneGame extends GameNotesDrawer
{
    public const ONE_PAGE_MAX_NROFSCORELINES = 6;

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
        $gamePlace = $game->getPlaces()->first();
        $roundNumber = $gamePlace === false ? '' : (string)$gamePlace->getGameRoundNumber();
        // $rectangle = new Rectangle(new HorizontalLine(new Point($x, $y), $width), -$height);
        $page->drawCell($roundNumber, $rectangle);
        return $rectangle->getBottom();
    }

//    protected function getPlaceWidth(TogetherGame $game): float
//    {
//        $margin = $this->config->getMargin();
//        $placesWidth = $this->getDetailPartWidth() + $margin + $this->getDetailPartWidth();
//        $placesWidth -= ($margin + $this->getPartWidth()); // unit(right side)
//        $placesWidth -= ($game->getPlaces()->count() - 1) * $margin;
//        return $placesWidth / $game->getPlaces()->count();
//    }

    protected function drawScore(GameNotesPage $page, AgainstGame|TogetherGame $game, HorizontalLine $top): void
    {
        if ($game instanceof AgainstGame) {
            return;
        }
        $structureNameService = $page->getParent()->getStructureNameService();
        $firstScoreConfig = $game->getScoreConfig();
        $calculateScoreConfig = $firstScoreConfig->getCalculate();
        $nrOfScoreLines = $this->getNrOfScoreLines($game->getRound(), $game->getCompetitionSport());
        $fontSize = $this->config->getFontHeight();
        $larger = 1.2;
        $largerFontSize = $fontSize * $larger;
        $height = $this->config->getRowHeight() * $larger;
        $leftPartWidth = $this->getLeftPartWidth($top);
        $placesStart = ToernooiPdfPage::PAGEMARGIN;
        $unitWidth = $this->getPartWidth($top);
        $unitStart = $this->getStartDetailLabel($top);

        $y = $top->getY();
        // 2x font thuis - uit
        $page->setFont($this->helper->getTimesFont(), $largerFontSize);
        $rectangle = new Rectangle(new HorizontalLine(new Point(ToernooiPdfPage::PAGEMARGIN, $y), $leftPartWidth), -$height);
        $page->drawCell('wedstrijd', $rectangle, Align::Right);

        // SCOREUNITS
        $descr = $this->getInputScoreConfigDescription($firstScoreConfig);
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $unitX = $unitStart;
            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines; $gameUnitNr++) {
                $rectangle = new Rectangle(new HorizontalLine(new Point($unitX, $y), $unitWidth), -$height);
                $page->drawCell($descr, $rectangle, Align::Right);
                $unitX += $unitWidth + $this->config->getMargin();
            }
        } else {
            $rectangle = new Rectangle(new HorizontalLine(new Point($unitStart, $y), $unitWidth), -$height);
            $page->drawCell($descr, $rectangle, Align::Right);
        }
        $y -= 2 * $height;

        // COMPETITORS
        $page->setFont($this->helper->getTimesFont(), $fontSize);
        $dots = '...............';
        foreach ($game->getPlaces() as $gamePlace) {
            $name = $structureNameService->getPlaceFromName($gamePlace->getPlace(), true, true);
            $rectangle = new Rectangle(new HorizontalLine(new Point($placesStart, $y), $leftPartWidth), -$height);
            $page->drawCell($name, $rectangle, Align::Right);
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
                    $rectangle = new Rectangle(new HorizontalLine(new Point($unitX, $y), $unitWidth), -$height);
                    $page->drawCell($dots, $rectangle, Align::Right);
                    $unitX += $unitWidth + $this->config->getMargin();
                }
            } else {
//                $this->drawCell('score', self::PAGEMARGIN, $y, $leftPartWidth, $height, Align::Right);
//                $placesX = $placesStart;
//                // loop door de scoreunits heen
                $rectangle = new Rectangle(new HorizontalLine(new Point($unitStart, $y), $unitWidth), -$height);
                $page->drawCell($dots, $rectangle, Align::Right);
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
