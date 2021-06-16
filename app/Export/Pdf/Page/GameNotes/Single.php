<?php
declare(strict_types=1);

namespace App\Export\Pdf\Page\GameNotes;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document;
use App\Export\Pdf\Page\GameNotes;
use App\Export\Pdf\Page\GameNotes as GameNotesBase;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Together as TogetherGame;
use Sports\Game\Place\Together as TogetherGamePlace;

class Single extends GameNotesBase
{
    public function __construct(Document $document, mixed $param1, TogetherGame $gameOne, TogetherGame|null $gameTwo)
    {
        parent::__construct($document, $param1, $gameOne, $gameTwo);
    }

    protected function drawPlaces(AgainstGame|TogetherGame $game, float $x, float $y, float $width, float $height): void
    {
        if ($game instanceof AgainstGame) {
            return;
        }
        $nameService = $this->getParent()->getNameService();
        $places = array_values($game->getPlaces()->toArray());
        $description = $nameService->getPlacesFromName($places, false, count($places) <= 3);
        $this->drawCell($description, $x, $y, $width, $height);
    }

    protected function drawGameRoundNumber(AgainstGame|TogetherGame $game, float $x, float $y, float $width, float $height): float
    {
        if ($game instanceof AgainstGame) {
            return $y;
        }
        $grs = array_map(fn (TogetherGamePlace $gp): string => (string)$gp->getGameRoundNumber(), $game->getPlaces()->toArray());
        $this->drawCell(implode(' & ', $grs), $x, $y, $width, $height);
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
        $nameService = $this->getParent()->getNameService();
        $roundNumber = $game->getRound()->getNumber();
        $planningConfig = $roundNumber->getValidPlanningConfig();
        $firstScoreConfig = $game->getScoreConfig();
        $fontSize = $this->getParent()->getFontHeight();
        $larger = 1.2;
        $largerFontSize = $this->getParent()->getFontHeight() * $larger;
        $height = GameNotes::RowHeight * $larger;
        $leftPartWidth = $this->getLeftPartWidth();
        $placesStart = $this->getStartDetailLabel();
        $placeWidth = $this->getPlaceWidth($game);
        $unitWidth = $this->getPartWidth();
        $unitStart = $placesStart;
        foreach ($game->getPlaces() as $gamePlace) {
            $unitStart += $placeWidth + GameNotes::Margin;
        }

        // 2x font thuis - uit
        $this->setFont($this->getParent()->getFont(), $largerFontSize);
        $this->drawCell('wedstrijd', $this->getPageMargin(), $y, $leftPartWidth, $height, Align::Right);

        // COMPETITORS
        $competitorFontSize = $game->getPlaces()->count() > 3 ? $fontSize : $largerFontSize;
        $this->setFont($this->getParent()->getFont(), $competitorFontSize);
        $placesX = $placesStart;
        foreach ($game->getPlaces() as $gamePlace) {
            $name = $nameService->getPlaceFromName($gamePlace->getPlace(), true, true);
            $placesX = $this->drawCell($name, $placesX, $y, $placeWidth + GameNotes::Margin, $height);
        }
        $y -= 2 * $height;


        $this->setFont($this->getParent()->getFont(), $largerFontSize);

        $calculateScoreConfig = $firstScoreConfig->getCalculate();
        $nrOfScoreLines = $this->getNrOfScoreLines($game->getRound(), $game->getCompetitionSport());
        $dots = '...............';

        // DOTS
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $yDelta = 0;

            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines; $gameUnitNr++) {
                $descr = $this->translationService->getScoreNameSingular($calculateScoreConfig) . ' ' . $gameUnitNr;
                $this->drawCell($descr, $this->getPageMargin(), $y - $yDelta, $leftPartWidth, $height, Align::Right);

                $placesX = $placesStart;
                foreach ($game->getPlaces() as $gamePlace) {
                    $placesX = $this->drawCell($dots, $placesX, $y - $yDelta, $placeWidth, $height);
                    $placesX += GameNotes::Margin;
                }

                $yDelta += $height;
            }
        } else {
            $this->drawCell('score', $this->getPageMargin(), $y, $leftPartWidth, $height, Align::Right);
            $placesX = $placesStart;
            foreach ($game->getPlaces() as $gamePlace) {
                $placesX = $this->drawCell($dots, $placesX, $y, $placeWidth, $height, Align::Left);
                $placesX += GameNotes::Margin;
            }
        }

        // SCOREUNITS
        $descr = $this->getInputScoreConfigDescription($firstScoreConfig);
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $yDelta = 0;
            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines; $gameUnitNr++) {
                $this->drawCell($descr, $unitStart, $y - $yDelta, $unitWidth, $height, Align::Right);
                $yDelta += $height;
            }
            $y -= $yDelta;
        } else {
            $this->drawCell($descr, $unitStart, $y, $unitWidth, $height, Align::Right);
        }

        $y -= $height; // extra lege regel

        if ($planningConfig->getExtension()) {
            $this->drawCell('na verleng.', $this->getPageMargin(), $y, $leftPartWidth, $height, Align::Right);
            $placesX = $placesStart;
            foreach ($game->getPlaces() as $gamePlace) {
                $placesX = $this->drawCell($dots, $placesX, $y, $placeWidth, $height);
                $placesX += GameNotes::Margin;
            }

            $name = $this->translationService->getScoreNamePlural($firstScoreConfig);
            $this->drawCell($name, $unitStart, $y, $unitWidth, $height, Align::Right);
        }
    }
}
