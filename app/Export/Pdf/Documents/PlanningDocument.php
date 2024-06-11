<?php

declare(strict_types=1);

namespace App\Export\Pdf\Documents;

use App\Export\Pdf\Configs\GameLineConfig;
use App\Export\Pdf\Configs\GamesConfig;
use App\Export\Pdf\Document as PdfDocument;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Page as PdfPage;
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
use SportsHelpers\Sport\Variant\AllInOneGame as AllInOneGameSportVariant;
use Zend_Pdf_Exception;
use Zend_Pdf_Page;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
abstract class PlanningDocument extends PdfDocument
{
    public function __construct(
        Tournament $tournament,
        Structure $structure,
        ImagePathResolver $imagePathResolver,
        PdfProgress $progress,
        float $maxSubjectProgress,
        protected GamesConfig $gameConfig,
        protected GameLineConfig $gameLineConfig
    ) {
        parent::__construct($tournament, $structure, $imagePathResolver, $progress, $maxSubjectProgress);
    }


    public function getGamesConfig(): GamesConfig
    {
        return $this->gameConfig;
    }

    public function getGameLineConfig(): GameLineConfig
    {
        return $this->gameLineConfig;
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

    protected function getPlanningPageDimension(RoundNumber $roundNumber): string
    {
        $selfRefereeEnabled = $roundNumber->getValidPlanningConfig()->selfRefereeEnabled();
        if ($selfRefereeEnabled) {
            return Zend_Pdf_Page::SIZE_A4_LANDSCAPE;
        }
        foreach ($roundNumber->getCompetitionSports() as $competitionSport) {
            $sportVariant = $competitionSport->createVariant();
            if ($sportVariant instanceof AllInOneGameSportVariant) {
                if ($this->getMaxNrOfPlacesPerPoule($roundNumber) > 3) {
                    return Zend_Pdf_Page::SIZE_A4_LANDSCAPE;
                }
            } elseif ($sportVariant->getNrOfGamePlaces() > 3) {
                return Zend_Pdf_Page::SIZE_A4_LANDSCAPE;
            }
        }
        return Zend_Pdf_Page::SIZE_A4;
    }

    protected function getMaxNrOfPlacesPerPoule(RoundNumber $roundNumber): int
    {
        $nrOfPlacesPerPoule = 0;
        foreach ($roundNumber->getPoules() as $poule) {
            if ($poule->getPlaces()->count() > $nrOfPlacesPerPoule) {
                $nrOfPlacesPerPoule = $poule->getPlaces()->count();
            }
        }
        return $nrOfPlacesPerPoule;
    }

    protected function drawPlanningPerFieldOrPouleHelper(
        RoundNumber $roundNumber,
        PlanningPage $page,
        HorizontalLine $horLine,
        bool $recursive
    ): void {
        $gameHorStartLine = $page->drawRoundNumberHeader($roundNumber, $horLine);
        $games = $page->getFilteredGames($roundNumber);
        if (count($games) > 0) {
            $page->initGameLines($roundNumber);
            $rectangle = new Rectangle($gameHorStartLine, -$this->getGameLineConfig()->getRowHeight());
            $page->drawGamesHeader($roundNumber, $rectangle);
            $gameHorStartLine = $rectangle->getBottom();
        }
        $logoPath = $this->getTournamentLogoPath(ImageSize::Small);
        $games = $roundNumber->getGames(GameOrder::ByDate);
        $recessHelper = new RecessHelper($roundNumber);
        $recesses = $recessHelper->getRecesses($this->tournament);
        foreach ($games as $game) {
            $gameHeight = $page->getParent()->getGameLineConfig()->getRowHeight();
            $recessToDraw = $recessHelper->removeRecessBeforeGame($game, $recesses);
            $gameHeight += $recessToDraw !== null ? $gameHeight : 0;
            if ($gameHorStartLine->getY() - $gameHeight < PdfPage::PAGEMARGIN) {
                // $field = $page->getFieldFilter();
                $page = $this->createPagePlanning($roundNumber, $page->getTitle());
                $y = $page->drawHeader($this->getTournament()->getName(), $logoPath, $page->getTitle());
                $page->setGameFilter($page->getGameFilter());
                $page->initGameLines($roundNumber);
                $rectangle = new Rectangle(
                    new HorizontalLine(
                        new Point(ToernooiPdfPage::PAGEMARGIN, $y),
                        $horLine->getWidth()
                    ),
                    -$this->getGameLineConfig()->getRowHeight()
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
            $gameHorStartLine = $page->drawGame($game, $gameHorStartLine);
        }

        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null && $recursive) {
            $gameHorStartLine = $gameHorStartLine->addY(-20);
            $this->drawPlanningPerFieldOrPouleHelper($nextRoundNumber, $page, $gameHorStartLine, $recursive);
        }
    }
}
