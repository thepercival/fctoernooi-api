<?php

declare(strict_types=1);

namespace App\Export\Pdf\Document\Planning;

use App\Export\Pdf\Configs\GameLineConfig;
use App\Export\Pdf\Configs\GamesConfig;
use App\Export\Pdf\Document\Planning as PdfPlanningDocument;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page;
use App\Export\Pdf\Page\Planning as PlanningPage;
use App\Export\Pdf\Point;
use App\Export\Pdf\RecessHelper;
use App\Export\Pdf\Rectangle;
use App\Export\PdfProgress;
use FCToernooi\Tournament;
use Sports\Game\Order as GameOrder;
use Sports\Round\Number as RoundNumber;
use Sports\Structure;
use Zend_Pdf_Exception;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Games extends PdfPlanningDocument
{
    public function __construct(
        protected Tournament $tournament,
        protected Structure $structure,
        protected string $url,
        protected PdfProgress $progress,
        protected float $maxSubjectProgress,
        GamesConfig $gamesConfig,
        GameLineConfig $gameLineConfig
    ) {
        parent::__construct(
            $tournament,
            $structure,
            $url,
            $progress,
            $maxSubjectProgress,
            $gamesConfig,
            $gameLineConfig
        );
    }

    protected function fillContent(): void
    {
        $firstRoundNumber = $this->structure->getFirstRoundNumber();
        $title = 'wedstrijden';
        $page = $this->createPagePlanning($firstRoundNumber, $title);
        $y = $page->drawHeader($this->getTournament()->getName(), $title);
        $horLine = new HorizontalLine(new Point(Page::PAGEMARGIN, $y), $page->getWidth());
        $this->drawPlanning($firstRoundNumber, $page, $horLine);
    }

    /**
     * @param RoundNumber $roundNumber
     * @param string $title
     * @return PlanningPage
     * @throws Zend_Pdf_Exception
     */
    protected function createPagePlanning(RoundNumber $roundNumber, string $title): PlanningPage
    {
        $page = new PlanningPage($this, $this->getPlanningPageDimension($roundNumber), $title);
        $this->pages[] = $page;
        return $page;
    }

    protected function drawPlanning(RoundNumber $roundNumber, PlanningPage $page, HorizontalLine $horLine): void
    {
        $games = $page->getFilteredGames($roundNumber);
        if (count($games) > 0) {
            $gamesHeaderStartLine = $page->drawRoundNumberHeader($roundNumber, $horLine);
            $page->initGameLines($roundNumber);
            $rectangle = new Rectangle($gamesHeaderStartLine, $this->getGameLineConfig()->getRowHeight());
            $page->drawGamesHeader($roundNumber, $rectangle);
            $gameHorStartLine = $rectangle->getBottom();
        } else {
            $gameHorStartLine = $horLine;
        }

        $games = $roundNumber->getGames(GameOrder::ByDate);
        $recessHelper = new RecessHelper($roundNumber);
        $recesses = $recessHelper->getRecesses($this->tournament);
        foreach ($games as $game) {
            $gameHeight = $page->getRowHeight();
            $recessToDraw = $recessHelper->removeRecessBeforeGame($game, $recesses);
            $gameHeight += $recessToDraw !== null ? $gameHeight : 0;
            if ($gameHorStartLine->getY() - $gameHeight < Page::PAGEMARGIN) {
                $title = 'wedstrijden';
                $page = $this->createPagePlanning($roundNumber, $title);
                $y = $page->drawHeader($this->getTournament()->getName(), $title);
                $page->initGameLines($roundNumber);
                $rectangle = new Rectangle(
                    new HorizontalLine(
                        new Point(Page::PAGEMARGIN, $y), $horLine->getWidth()
                    ),
                    $this->getGameLineConfig()->getRowHeight()
                );
                $page->drawGamesHeader($roundNumber, $rectangle);
                $gameHorStartLine = $rectangle->getBottom();
            }
            if ($recessToDraw !== null) {
                // $page->initGameLines($roundNumber);
                $rectangle = new Rectangle($gameHorStartLine, $this->getGameLineConfig()->getRowHeight());
                $page->drawRecess($game, $recessToDraw, $rectangle);
                $gameHorStartLine = $rectangle->getBottom();
            }
            $gameHorStartLine = $page->drawGame($game, $gameHorStartLine, true);
        }
        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $gameHorStartLine = $gameHorStartLine->addY(-20);
            $this->drawPlanning($nextRoundNumber, $page, $gameHorStartLine);
        }
    }
}
