<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\GameNote;

use App\Export\Pdf\Align;
use App\Export\Pdf\Drawers\GameNote as GameNotesDrawer;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page;
use App\Export\Pdf\Page\GameNotes;
use App\Export\Pdf\Page\GameNotes as GameNotesPage;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Together as TogetherGame;
use SportsHelpers\Against\Side as AgainstSide;

class Against extends GameNotesDrawer
{
    protected function drawPlaces(GameNotesPage $page, AgainstGame|TogetherGame $game, Rectangle $rectangle): void
    {
        if ($game instanceof TogetherGame) {
            return;
        }
        $structureNameService = $page->getParent()->getStructureNameService();
        $homePlaces = $game->getSidePlaces(AgainstSide::Home);
        $home = $structureNameService->getPlacesFromName($homePlaces, false, count($homePlaces) === 1);
        $awayPlaces = $game->getSidePlaces(AgainstSide::Away);
        $away = $structureNameService->getPlacesFromName($awayPlaces, false, count($awayPlaces) === 1);
        // $rectangle = new Rectangle(new HorizontalLine(new Point($x, $y), $width), -$height);
        $page->drawCell($home . ' - ' . $away, $rectangle);
    }

    protected function drawGameRoundNumber(
        GameNotesPage $page,
        AgainstGame|TogetherGame $game,
        Rectangle $rectangle
    ): HorizontalLine {
        if ($game instanceof TogetherGame) {
            return $rectangle->getTop();
        }
        $gameRoundNumber = (string)$game->getGameRoundNumber();
        // $rectangle = new Rectangle(new HorizontalLine(new Point($x, $y), $width), -$height);
        $page->drawCell($gameRoundNumber, $rectangle);
        return $rectangle->getBottom();
    }

    protected function drawScore(GameNotesPage $page, AgainstGame|TogetherGame $game, HorizontalLine $top): void
    {
        if ($game instanceof TogetherGame) {
            return;
        }
        $structureNameService = $page->getParent()->getStructureNameService();
        $roundNumber = $game->getRound()->getNumber();
        $planningConfig = $roundNumber->getValidPlanningConfig();
        $firstScoreConfig = $game->getScoreConfig();
        $margin = $this->config->getMargin();
        $larger = 1.2;
        $height = $this->config->getRowHeight() * $larger;
        $leftPartWidth = $this->getLeftPartWidth($top);
        $homeStart = $this->getStartDetailLabel($top);
        $sideWidth = $this->getDetailPartWidth($top);
        $sepStartX = $homeStart + $sideWidth;
        $awayStart = $this->getStartDetailValue($top);
        $dotsWidth = $this->getPartWidth($top);
        $unitStart = $awayStart + $dotsWidth + $margin;
        $unitWidth = $this->getPartWidth($top);
//        $detailValueWidth = $this->getDetailValueWidth($x);
//        $x2 = $this->getXSecondBorder() + ($margin * 0.5);

        $y = $top->getY();
        // 2x font thuis - uit
        $page->setFont($this->helper->getTimesFont(), $this->config->getFontHeight() * $larger);
        $rectangle = new Rectangle(new HorizontalLine(new Point(Page::PAGEMARGIN, $y), $leftPartWidth), -$height);
        $page->drawCell('wedstrijd', $rectangle, Align::Right);

        // COMPETITORS
        $home = $structureNameService->getPlacesFromName($game->getSidePlaces(AgainstSide::Home), true, true);
        $rectangle = new Rectangle(new HorizontalLine(new Point($homeStart, $y), $sideWidth), -$height);
        $page->drawCell($home, $rectangle, Align::Right);
        $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), -$height);
        $page->drawCell('-', $rectangle, Align::Center);
        $away = $structureNameService->getPlacesFromName($game->getSidePlaces(AgainstSide::Away), true, true);
        $rectangle = new Rectangle(new HorizontalLine(new Point($awayStart, $y), $sideWidth), -$height);
        $page->drawCell($away, $rectangle);
        $y -= 2 * $height;

        $page->setFont($this->helper->getTimesFont(), $this->config->getFontHeight() * $larger);

        $calculateScoreConfig = $firstScoreConfig->getCalculate();

        $dots = '...............';
        $nrOfScoreLines = $this->getNrOfScoreLines($game->getRound(), $game->getCompetitionSport());

        // DOTS
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $yDelta = 0;
            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines; $gameUnitNr++) {
                $descr = $this->translationService->getScoreNameSingular($calculateScoreConfig) . ' ' . $gameUnitNr;
                $rectangle = new Rectangle(
                    new HorizontalLine(new Point(Page::PAGEMARGIN, $y - $yDelta), $leftPartWidth),
                    -$height
                );
                $page->drawCell($descr, $rectangle, Align::Right);
                $rectangle = new Rectangle(
                    new HorizontalLine(new Point($homeStart, $y - $yDelta), $sideWidth),
                    -$height
                );
                $page->drawCell($dots, $rectangle, Align::Right);
                $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y - $yDelta), $margin), -$height);
                $page->drawCell('-', $rectangle, Align::Center);
                $rectangle = new Rectangle(
                    new HorizontalLine(new Point($awayStart, $y - $yDelta), $sideWidth),
                    -$height
                );
                $page->drawCell($dots, $rectangle);
                $yDelta += $height;
            }
        } else {
            $rectangle = new Rectangle(new HorizontalLine(new Point(Page::PAGEMARGIN, $y), $leftPartWidth), -$height);
            $page->drawCell('score', $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($homeStart, $y), $sideWidth), -$height);
            $page->drawCell($dots, $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), -$height);
            $page->drawCell('-', $rectangle, Align::Center);
            $rectangle = new Rectangle(new HorizontalLine(new Point($awayStart, $y), $sideWidth), -$height);
            $page->drawCell($dots, $rectangle);
        }

        // SCOREUNITS
        $descr = $this->getInputScoreConfigDescription($firstScoreConfig);
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $yDelta = 0;
            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines; $gameUnitNr++) {
                $rectangle = new Rectangle(
                    new HorizontalLine(new Point($unitStart, $y - $yDelta), $unitWidth),
                    -$height
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
            $rectangle = new Rectangle(new HorizontalLine(new Point(Page::PAGEMARGIN, $y), $leftPartWidth), -$height);
            $page->drawCell('na verleng.', $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($homeStart, $y), $sideWidth), -$height);
            $page->drawCell($dots, $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), -$height);
            $page->drawCell('-', $rectangle, Align::Center);
            $rectangle = new Rectangle(new HorizontalLine(new Point($awayStart, $y), $sideWidth), -$height);
            $page->drawCell($dots, $rectangle);

            $name = $this->translationService->getScoreNamePlural($firstScoreConfig);
            $rectangle = new Rectangle(new HorizontalLine(new Point($unitStart, $y), $unitWidth), -$height);
            $page->drawCell($name, $rectangle, Align::Right);
        }
    }
}
