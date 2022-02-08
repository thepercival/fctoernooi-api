<?php

declare(strict_types=1);

namespace App\Export\Pdf\GameLine;

use App\Export\Pdf\Align;
use App\Export\Pdf\GameLine;
use App\Export\Pdf\GameLine as GameLineBase;
use App\Export\Pdf\GameLine\Column\Against as AgainstColumn;
use App\Export\Pdf\Page;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Phase as GamePhase;
use Sports\Game\State as GameState;
use Sports\Game\Together as TogetherGame;
use SportsHelpers\Against\Side as AgainstSide;

class Against extends GameLineBase
{
    public function __construct(Page\Planning $page, int $shoDateTime, int $showReferee)
    {
        parent::__construct($page, $shoDateTime, $showReferee);
        $this->initColumnWidths();
    }

    protected function initColumnWidths(): void
    {
        parent::initColumnWidths();
        $this->columnWidths[AgainstColumn::Score] = 0.11;

        $this->columnWidths[AgainstColumn::SidePlaces] = $this->columnWidths[Column::PlacesAndScore];
        $this->columnWidths[AgainstColumn::SidePlaces] -= $this->columnWidths[AgainstColumn::Score];
        $this->columnWidths[AgainstColumn::SidePlaces] /= 2;
    }

    protected function drawPlacesAndScoreHeader(float $x, float $y): float
    {
        $height = $this->page->getRowHeight();
        $sideWidth = $this->getColumnWidth(AgainstColumn::SidePlaces);
        $scoreWidth = $this->getColumnWidth(AgainstColumn::Score);
        $x = $this->page->drawCell('thuis', $x, $y, $sideWidth, $height, Align::Right, 'black');
        $x = $this->page->drawCell('score', $x, $y, $scoreWidth, $height, Align::Center, 'black');
        return $this->page->drawCell('uit', $x, $y, $sideWidth, $height, Align::Left, 'black');
    }

    protected function drawPlacesAndScoreCell(AgainstGame|TogetherGame $game, float $x, float $y): float
    {
        if ($game instanceof TogetherGame) {
            return $x;
        }
        $height = $this->page->getRowHeight();
        $sideWidth = $this->getColumnWidth(AgainstColumn::SidePlaces);
        $scoreWidth = $this->getColumnWidth(AgainstColumn::Score);

        $nameService = $this->page->getParent()->getNameService();

        $home = $nameService->getPlacesFromName($game->getSidePlaces(AgainstSide::Home), true, true);
        $x = $this->page->drawCell($home, $x, $y, $sideWidth, $height, Align::Right, 'black');

        $x = $this->drawCell($this->getScore($game), $x, $y, $scoreWidth, $height);

        $away = $nameService->getPlacesFromName($game->getSidePlaces(AgainstSide::Away), true, true);
        return $this->page->drawCell($away, $x, $y, $sideWidth, $height, Align::Left, 'black');
    }

    private function getScore(AgainstGame $game): string
    {
        $score = ' - ';
        if ($game->getState() !== GameState::Finished) {
            return $score;
        }
        $finalScore = $this->scoreConfigService->getFinalAgainstScore($game);
        if ($finalScore === null) {
            return $score;
        }
        $extension = $game->getFinalPhase() === GamePhase::ExtraTime ? '*' : '';
        return $finalScore->getHome() . $score . $finalScore->getAway() . $extension;
    }
}
