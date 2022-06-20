<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\GameLine;

use App\Export\Pdf\Align;
use App\Export\Pdf\Configs\GameLineConfig;
use App\Export\Pdf\Drawers\Gameline;
use App\Export\Pdf\Drawers\GameLine\Column\Against as AgainstColumn;
use App\Export\Pdf\Line\Vertical as VerticalLine;
use App\Export\Pdf\Page as PdfPage;
use App\Export\Pdf\Rectangle;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Phase as GamePhase;
use Sports\Game\State as GameState;
use Sports\Game\Together as TogetherGame;
use SportsHelpers\Against\Side as AgainstSide;

class Against extends GameLine
{
    public function __construct(PdfPage $page, GameLineConfig $config)
    {
        parent::__construct($page, $config);
        $this->initAgainstGameLineColumnWidths($config);
    }

    private function initAgainstGameLineColumnWidths(GameLineConfig $config): void
    {
        $this->initColumnWidths($config);
        $this->columnWidths[AgainstColumn::Score->value] = 0.11;

        $this->columnWidths[AgainstColumn::SidePlaces->value] = $this->columnWidths[Column::PlacesAndScore->value];
        $this->columnWidths[AgainstColumn::SidePlaces->value] -= $this->columnWidths[AgainstColumn::Score->value];
        $this->columnWidths[AgainstColumn::SidePlaces->value] /= 2;
    }

    protected function drawPlacesAndScoreHeader(VerticalLine $left, GameLineConfig $config): VerticalLine
    {
        $sideWidth = $this->getColumnWidth(AgainstColumn::SidePlaces);
        $scoreWidth = $this->getColumnWidth(AgainstColumn::Score);
        $homeCell = new Rectangle($left, $sideWidth);
        $this->page->drawCell('thuis', $homeCell, Align::Right, 'black');
        $scoreCell = new Rectangle($homeCell->getRight(), $scoreWidth);
        $this->page->drawCell('score', $scoreCell, Align::Center, 'black');
        $awayCell = new Rectangle($scoreCell->getRight(), $sideWidth);
        $this->page->drawCell('uit', $awayCell, Align::Left, 'black');
        return $awayCell->getRight();
    }

    protected function drawPlacesAndScoreCell(AgainstGame|TogetherGame $game, VerticalLine $left): VerticalLine
    {
        if ($game instanceof TogetherGame) {
            return $left;
        }
        $sideWidth = $this->getColumnWidth(AgainstColumn::SidePlaces);
        $scoreWidth = $this->getColumnWidth(AgainstColumn::Score);

        $structureNameService = $this->getParent()->getStructureNameService();

        $home = $structureNameService->getPlacesFromName($game->getSidePlaces(AgainstSide::Home), true, true);
        $homeCell = new Rectangle($left, $sideWidth);
        $this->page->drawCell($home, $homeCell, Align::Right, 'black');

        $scoreCell = new Rectangle($homeCell->getRight(), $scoreWidth);
        $this->drawCell($this->getScore($game), $scoreCell);

        $away = $structureNameService->getPlacesFromName($game->getSidePlaces(AgainstSide::Away), true, true);
        $awayCell = new Rectangle($scoreCell->getRight(), $sideWidth);
        $this->page->drawCell($away, $awayCell, Align::Left, 'black');
        return $awayCell->getRight();
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
