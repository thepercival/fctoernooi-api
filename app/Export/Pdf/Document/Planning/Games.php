<?php

declare(strict_types=1);

namespace App\Export\Pdf\Document\Planning;

use App\Export\Pdf\Document\Planning as PdfPlanningDocument;
use App\Export\Pdf\Page\Planning as PagePlanning;
use App\Export\Pdf\Page\Planning as PlanningPage;
use App\Export\Pdf\RecessHelper;
use Sports\Game\Order as GameOrder;
use Sports\Round\Number as RoundNumber;
use Zend_Pdf_Exception;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Games extends PdfPlanningDocument
{
    protected function fillContent(): void
    {
        $firstRoundNumber = $this->structure->getFirstRoundNumber();
        $title = 'wedstrijden';
        $page = $this->createPagePlanning($firstRoundNumber, $title);
        $y = $page->drawHeader($title);
        $this->drawPlanning($firstRoundNumber, $page, $y);
    }

    /**
     * @param RoundNumber $roundNumber
     * @param string $title
     * @return PlanningPage
     * @throws Zend_Pdf_Exception
     */
    protected function createPagePlanning(RoundNumber $roundNumber, string $title): PlanningPage
    {
        $page = new PlanningPage($this, $this->getPlanningPageDimension($roundNumber));
        $page->setFont($this->getFont(), $this->getFontHeight());
        $page->setTitle($title);
        $this->pages[] = $page;
        return $page;
    }

    protected function drawPlanning(RoundNumber $roundNumber, PagePlanning $page, float $y): void
    {
        $games = $page->getFilteredGames($roundNumber);
        if (count($games) > 0) {
            $y = $page->drawRoundNumberHeader($roundNumber, $y);
            $page->initGameLines($roundNumber);
            $y = $page->drawGamesHeader($roundNumber, $y);
        }

//        $games = $page->getGames($roundNumber);
//        if (count($games) > 0) {
//            $y = $page->drawGamesHeader($roundNumber, $y);
//        }
        $games = $roundNumber->getGames(GameOrder::ByDate);
        $recessHelper = new RecessHelper($roundNumber);
        $recesses = $recessHelper->getRecesses($this->tournament);
        foreach ($games as $game) {
            $gameHeight = $page->getRowHeight();
            $recessPeriodToDraw = $recessHelper->removeRecessBeforeGame($game, $recesses);
            $gameHeight += $recessPeriodToDraw !== null ? $gameHeight : 0;
            if ($y - $gameHeight < PagePlanning::PAGEMARGIN) {
                $title = 'wedstrijden';
                $page = $this->createPagePlanning($roundNumber, $title);
                $y = $page->drawHeader($title);
                $page->initGameLines($roundNumber);
                $y = $page->drawGamesHeader($roundNumber, $y);
            }
            if ($recessPeriodToDraw !== null) {
                $page->initGameLines($roundNumber);
                $y = $page->drawRecess($roundNumber, $game, $recessPeriodToDraw, $y);
            }
            $y = $page->drawGame($game, $y, true);
        }
        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $y -= 20;
            $this->drawPlanning($nextRoundNumber, $page, $y);
        }
    }
}
