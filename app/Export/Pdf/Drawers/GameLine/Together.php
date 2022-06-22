<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\GameLine;

use App\Export\Pdf\Align;
use App\Export\Pdf\Configs\GameLineConfig;
use App\Export\Pdf\Drawers\GameLine;
use App\Export\Pdf\Line\Vertical as VerticalLine;
use App\Export\Pdf\Page as PdfPage;
use App\Export\Pdf\Page\Traits\GameLine\Column;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Place\Together as TogetherGamePlace;
use Sports\Game\State as GameState;
use Sports\Game\Together as TogetherGame;
use Sports\Round\Number as RoundNumber;

class Together extends GameLine
{
    public function __construct(PdfPage $page, GameLineConfig $config, RoundNumber $roundNumber)
    {
        parent::__construct($page, $config, $roundNumber);
    }

    protected function getPlaceWidth(int $nrOfGamePlaces): float
    {
        if ($nrOfGamePlaces > $this->config->getMaxNrOfPlacesPerLine()) {
            $nrOfGamePlaces = $this->config->getMaxNrOfPlacesPerLine();
        }
        $width = $this->getColumnWidth(Column::PlacesAndScore);
        return $width / $nrOfGamePlaces;
    }

    protected function drawPlacesAndScoreHeader(VerticalLine $left): VerticalLine
    {
        $width = $this->getColumnWidth(Column::PlacesAndScore);
        $this->page->drawCell('deelnemers & score', new Rectangle($left, $width), Align::Center, 'black');
        return $left->addX($width);
    }

    protected function drawPlacesAndScoreCell(AgainstGame|TogetherGame $game, VerticalLine $left): VerticalLine
    {
        // HIER MOETEN DE SCORES OOK VERWERKT WORDEN IN DE PLACES!!!
        if ($game instanceof AgainstGame) {
            return $left;
        }
        $structureNameService = $this->page->getStructureNameService();
        $height = $this->config->getRowHeight();
        $placeWidth = $this->getPlaceWidth($game->getPlaces()->count());
        $placeNameWidth = $placeWidth;
        $scoreWidth = 0;
        if ($game->getState() === GameState::Finished) {
            $scoreWidth = 25;
        }
        $placeNameWidth -= $scoreWidth;

        $placeCounter = 1;
        $xStart = $left->getX();
        foreach ($game->getPlaces() as $gamePlace) {
            $placeName = $structureNameService->getPlaceFromName($gamePlace->getPlace(), true, true);
            $rectangle = new Rectangle($left, $placeNameWidth);
            $this->page->drawCell($placeName, $rectangle, Align::Left, 'black');
            $left = $rectangle->getRight();
            if ($game->getState() === GameState::Finished) {
                $score = $this->getScore($gamePlace);
                $rectangle = new Rectangle($left, $scoreWidth);
                $this->page->drawCell($score, $rectangle, Align::Right, 'black');
                $left = $rectangle->getRight();
            }
            if ($placeCounter++ % $this->config->getMaxNrOfPlacesPerLine() === 0) {
                $left = new VerticalLine(new Point($xStart, $left->getEnd()->getY() - $height), $left->getHeight());
            }
        }


//        $height = $this->page->getRowHeight();
//        $sideWidth = $this->getColumnWidth(AgainstColumn::SidePlaces);
//        $scoreWidth = $this->getColumnWidth(AgainstColumn::Score);
//

//
//        $home = $nameService->getPlacesFromName($game->getSidePlaces(AgainstSide::HOME), true, true);
//        $x = $this->page->drawCell($home, $x, $y, $sideWidth, $height, Align::Right, 'black');
//
//        $x = $this->drawCell($this->getScore($game), $x, $y, $scoreWidth, $height);
//
//        $away = $nameService->getPlacesFromName($game->getSidePlaces(AgainstSide::AWAY), true, true);
//        return $this->page->drawCell($away, $x, $y, $sideWidth, $height, Align::Left, 'black');
        return $left;
    }

    private function getScore(TogetherGamePlace $gamePlace): string
    {
        $score = '';
        if ($gamePlace->getGame()->getState() !== GameState::Finished) {
            return $score;
        }
        return (string)$this->scoreConfigService->getFinalTogetherScore($gamePlace);
    }
}
