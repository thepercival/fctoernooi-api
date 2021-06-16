<?php
declare(strict_types=1);

namespace App\Export\Pdf\GameLine;

use App\Export\Pdf\GameLine;
use App\Export\Pdf\Page;
use Sports\Game\Place\Together as TogetherGamePlace;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Together as TogetherGame;
use App\Export\Pdf\Align;
use App\Export\Pdf\GameLine as GameLineBase;
use Sports\State;

class Together extends GameLineBase
{
    public function __construct(Page\Planning $page, int $shoDateTime, int $showReferee)
    {
        parent::__construct($page, $shoDateTime, $showReferee);
        $this->initColumnWidths();
    }

    protected function initColumnWidths(): void
    {
        parent::initColumnWidths();
    }

    protected function getPlaceWidth(int $nrOfGamePlaces): float {
        if( $nrOfGamePlaces > GameLine::MaxNrOfPlacesPerLine) {
            $nrOfGamePlaces = GameLine::MaxNrOfPlacesPerLine;
        }
        $width = $this->getColumnWidth(Column::PlacesAndScore);
        return $width / $nrOfGamePlaces;
    }

    protected function drawPlacesAndScoreHeader(float $x, float $y): float
    {
        $height = $this->page->getRowHeight();
        $width = $this->getColumnWidth(Column::PlacesAndScore);
        return $this->page->drawCell('deelnemers & score', $x, $y, $width, $height, Align::Center, 'black');
    }

    protected function drawPlacesAndScoreCell(AgainstGame|TogetherGame $game, float $x, float $y): float
    {
        // HIER MOETEN DE SCORES OOK VERWERKT WORDEN IN DE PLACES!!!
        if ($game instanceof AgainstGame) {
            return $x;
        }
        $nameService = $this->page->getParent()->getNameService();
        $height = $this->page->getRowHeight();
        $placeWidth = $this->getPlaceWidth($game->getPlaces()->count());
        $placeNameWidth = $placeWidth;
        $scoreWidth = 0;
        if ($game->getState() === State::Finished) {
            $scoreWidth = 25;
        }
        $placeNameWidth -= $scoreWidth;

        $placeCounter = 1;
        $xStart = $x;
        foreach ($game->getPlaces() as $gamePlace) {
            $placeName = $nameService->getPlaceFromName($gamePlace->getPlace(), true, true);
            $x = $this->page->drawCell($placeName, $x, $y, $placeNameWidth, $height, Align::Left, 'black');
            if ($game->getState() === State::Finished) {
                $score = $this->getScore($gamePlace);
                $x = $this->page->drawCell($score, $x, $y, $scoreWidth, $height, Align::Right, 'black');
            }
            if( $placeCounter++ % GameLine::MaxNrOfPlacesPerLine === 0 ) {
                $x = $xStart;
                $y -= $height;
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
        return $x;
    }

    private function getScore(TogetherGamePlace $gamePlace): string
    {
        $score = '';
        if ($gamePlace->getGame()->getState() !== State::Finished) {
            return $score;
        }
        return (string)$this->scoreConfigService->getFinalTogetherScore($gamePlace);
    }
}
