<?php

declare(strict_types=1);

namespace App\Export\Pdf\Documents\Planning;

use App\Export\Pdf\Configs\GameLineConfig;
use App\Export\Pdf\Configs\GamesConfig;
use App\Export\Pdf\Documents\PlanningDocument as PdfPlanningDocument;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Pages;
use App\Export\Pdf\Pages\PlanningPage as PlanningPage;
use App\Export\Pdf\Point;
use App\Export\Pdf\RecessHelper;
use App\Export\Pdf\Rectangle;
use App\Export\PdfProgress;
use App\ImagePathResolver;
use App\ImageSize;
use FCToernooi\Tournament;
use Sports\Game\Order as GameOrder;
use Sports\Round\Number as RoundNumber;
use Sports\Structure;
use Zend_Pdf_Exception;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class GamesDocument extends PdfPlanningDocument
{
    public function __construct(
        Tournament $tournament,
        Structure $structure,
        ImagePathResolver $imagePathResolver,
        PdfProgress $progress,
        float $maxSubjectProgress,
        GamesConfig $gamesConfig,
        GameLineConfig $gameLineConfig
    ) {
        parent::__construct(
            $tournament,
            $structure,
            $imagePathResolver,
            $progress,
            $maxSubjectProgress,
            $gamesConfig,
            $gameLineConfig
        );
    }

    protected function renderCustom(): void
    {
        $firstRoundNumber = $this->structure->getFirstRoundNumber();
        $title = 'wedstrijden';
        $page = $this->createPagePlanning($firstRoundNumber, $title);
        $logoPath = $this->getTournamentLogoPath(ImageSize::Small);
        $y = $page->drawHeader($this->getTournament()->getName(), $logoPath, $title);
        $horLine = new HorizontalLine(new Point(ToernooiPdfPage::PAGEMARGIN, $y), $page->getDisplayWidth());
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

        $logoPath = $this->getTournamentLogoPath(ImageSize::Small);
        $games = $roundNumber->getGames(GameOrder::ByDate);
        $recessHelper = new RecessHelper($roundNumber);
        $recesses = $recessHelper->getRecesses($this->tournament);
        foreach ($games as $game) {
            $gameHeight = $page->getParent()->getGameLineConfig()->getRowHeight();
            $recessToDraw = $recessHelper->removeRecessBeforeGame($game, $recesses);
            $gameHeight += $recessToDraw !== null ? $gameHeight : 0;
            if ($gameHorStartLine->getY() - $gameHeight < ToernooiPdfPage::PAGEMARGIN) {
                $title = 'wedstrijden';
                $page = $this->createPagePlanning($roundNumber, $title);
                $y = $page->drawHeader($this->getTournament()->getName(), $logoPath, $title);
                $page->initGameLines($roundNumber);
                $rectangle = new Rectangle(
                    new HorizontalLine(
                        new Point(ToernooiPdfPage::PAGEMARGIN, $y), $horLine->getWidth()
                    ),
                    $this->getGameLineConfig()->getRowHeight()
                );
                $page->drawGamesHeader($roundNumber, $rectangle);
                $gameHorStartLine = $rectangle->getBottom();
            }
            if ($recessToDraw !== null) {
                // $page->initGameLines($roundNumber);
                $rectangle = new Rectangle($gameHorStartLine, -$this->getGameLineConfig()->getRowHeight());
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
