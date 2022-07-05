<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\GameLine;

use App\Export\Pdf\Align;
use App\Export\Pdf\Configs\GameLineConfig;
use App\Export\Pdf\Drawers\GameLine as GameLineBase;
use App\Export\Pdf\Drawers\GameLine\Column\Against as AgainstColumn;
use App\Export\Pdf\Line\Vertical as VerticalLine;
use App\Export\Pdf\Page as PdfPage;
use App\Export\Pdf\Rectangle;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Phase as GamePhase;
use Sports\Game\State as GameState;
use Sports\Game\Together as TogetherGame;
use Sports\Round\Number as RoundNumber;
use SportsHelpers\Against\Side as AgainstSide;

class Against extends GameLineBase
{
    public function __construct(PdfPage $page, GameLineConfig $config, RoundNumber $roundNumber)
    {
        parent::__construct($page, $config, $roundNumber);
        $this->initAgainstColumnWidths();
    }

    private function initAgainstColumnWidths(): void
    {
        $this->columnWidths[AgainstColumn::Score->value] = 0.11;

        $this->columnWidths[AgainstColumn::SidePlaces->value] = $this->columnWidths[Column::PlacesAndScore->value];
        $this->columnWidths[AgainstColumn::SidePlaces->value] -= $this->columnWidths[AgainstColumn::Score->value];
        $this->columnWidths[AgainstColumn::SidePlaces->value] /= 2;
    }

    protected function drawPlacesAndScoreHeader(VerticalLine $left): VerticalLine
    {
        $sideWidth = $this->getColumnWidth(AgainstColumn::SidePlaces);
        $scoreWidth = $this->getColumnWidth(AgainstColumn::Score);
        $homeCell = new Rectangle($left, $sideWidth);
        $this->drawHeaderCell('thuis', $homeCell, Align::Right);
        $scoreCell = new Rectangle($homeCell->getRight(), $scoreWidth);
        $this->drawHeaderCell('score', $scoreCell);
        $awayCell = new Rectangle($scoreCell->getRight(), $sideWidth);
        $this->drawHeaderCell('uit', $awayCell, Align::Left);
        return $awayCell->getRight();
    }

    protected function drawPlacesAndScoreCell(AgainstGame|TogetherGame $game, VerticalLine $left): VerticalLine
    {
        if ($game instanceof TogetherGame) {
            return $left;
        }
        $sideWidth = $this->getColumnWidth(AgainstColumn::SidePlaces);
        $scoreWidth = $this->getColumnWidth(AgainstColumn::Score);

        $structureNameService = $this->page->getStructureNameService();

        $home = $structureNameService->getPlacesFromName($game->getSidePlaces(AgainstSide::Home), true, true);
        $homeCell = new Rectangle($left, $sideWidth);
        $this->drawTableCell($home, $homeCell, Align::Right);

        $scoreCell = new Rectangle($homeCell->getRight(), $scoreWidth);
        $this->drawTableCell($this->getScore($game), $scoreCell);

        $away = $structureNameService->getPlacesFromName($game->getSidePlaces(AgainstSide::Away), true, true);
        $awayCell = new Rectangle($scoreCell->getRight(), $sideWidth);
        $this->drawTableCell($away, $awayCell, Align::Left);
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
