<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page\GameNotes;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document;
use App\Export\Pdf\Page\GameNotes;
use App\Export\Pdf\Page\GameNotes as GameNotesBase;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Together as TogetherGame;

class AllInOneGame extends GameNotesBase
{
    public const OnePageMaxNrOfScoreLines = 6;

    public function __construct(Document $document, mixed $param1, TogetherGame $gameOne, TogetherGame|null $gameTwo)
    {
        parent::__construct($document, $param1, $gameOne, $gameTwo);
    }

    protected function drawPlaces(AgainstGame|TogetherGame $game, float $x, float $y, float $width, float $height): void
    {
        if ($game instanceof AgainstGame) {
            return;
        }
        $structureNameService = $this->parent->getStructureNameService();
        $places = array_values($game->getPlaces()->toArray());
        $description = $structureNameService->getPlacesFromName($places, false, count($places) <= 3);
        $this->drawCell($description, $x, $y, $width, $height);
    }

    protected function drawGameRoundNumber(AgainstGame|TogetherGame $game, float $x, float $y, float $width, float $height): float
    {
        if ($game instanceof AgainstGame) {
            return $y;
        }
        $gamePlace = $game->getPlaces()->first();
        $roundNumber = $gamePlace === false ? '' : (string)$gamePlace->getGameRoundNumber();
        $this->drawCell($roundNumber, $x, $y, $width, $height);
        return $y - $height;
    }

    protected function getPlaceWidth(TogetherGame $game): float
    {
        $placesWidth = $this->getDetailPartWidth() + GameNotes::Margin + $this->getDetailPartWidth();
        $placesWidth -=  (GameNotes::Margin + $this->getPartWidth()); // unit(right side)
        $placesWidth -= ($game->getPlaces()->count() - 1) * GameNotes::Margin;
        return $placesWidth / $game->getPlaces()->count();
    }

    protected function drawScore(AgainstGame|TogetherGame $game, float $y): void
    {
        if ($game instanceof AgainstGame) {
            return;
        }
        $structureNameService = $this->parent->getStructureNameService();
        $roundNumber = $game->getRound()->getNumber();
        $planningConfig = $roundNumber->getValidPlanningConfig();
        $firstScoreConfig = $game->getScoreConfig();
        $calculateScoreConfig = $firstScoreConfig->getCalculate();
        $nrOfScoreLines = $this->getNrOfScoreLines($game->getRound(), $game->getCompetitionSport());
        $fontSize = $this->parent->getFontHeight();
        $larger = 1.2;
        $largerFontSize = $this->parent->getFontHeight() * $larger;
        $height = GameNotes::RowHeight * $larger;
        $leftPartWidth = $this->getLeftPartWidth();
        $placesStart = self::PAGEMARGIN;
        $placeWidth = $this->getPlaceWidth($game);
        $unitWidth = $this->getPartWidth();
        $unitStart = $this->getStartDetailLabel();

        // 2x font thuis - uit
        $this->setFont($this->helper->getTimesFont(), $largerFontSize);
        $this->drawCell('wedstrijd', self::PAGEMARGIN, $y, $leftPartWidth, $height, Align::Right);

        // SCOREUNITS
        $descr = $this->getInputScoreConfigDescription($firstScoreConfig);
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $unitX = $unitStart;
            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines; $gameUnitNr++) {
                $this->drawCell($descr, $unitX, $y, $unitWidth, $height, Align::Right);
                $unitX += $unitWidth + GameNotes::Margin;
            }
        } else {
            $this->drawCell($descr, $unitStart, $y, $unitWidth, $height, Align::Right);
        }
        $y -= 2 * $height;

        // COMPETITORS
        $this->setFont($this->helper->getTimesFont(), $fontSize);
        $dots = '...............';
        foreach ($game->getPlaces() as $gamePlace) {
            $name = $structureNameService->getPlaceFromName($gamePlace->getPlace(), true, true);
            $x = $this->drawCell($name, $placesStart, $y, $leftPartWidth, $height, Align::Right);
            $x += GameNotes::Margin;

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
                    $this->drawCell($dots, $unitX, $y, $unitWidth, $height, Align::Right);
                    $unitX += $unitWidth + GameNotes::Margin;
                }
            } else {
//                $this->drawCell('score', self::PAGEMARGIN, $y, $leftPartWidth, $height, Align::Right);
//                $placesX = $placesStart;
//                // loop door de scoreunits heen

                $this->drawCell($dots, $unitStart, $y, $unitWidth, $height, Align::Right);
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
