<?php

declare(strict_types=1);

namespace App\Export\Pdf\Document;

use App\Export\Pdf\Document as PdfDocument;
use App\Export\Pdf\Page as PdfPage;
use App\Export\Pdf\Page\Planning as PagePlanning;
use App\Export\Pdf\RecessHelper;
use Sports\Game\Order as GameOrder;
use Sports\Round\Number as RoundNumber;
use SportsHelpers\Sport\Variant\AllInOneGame as AllInOneGameSportVariant;
use Zend_Pdf_Exception;
use Zend_Pdf_Page;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
abstract class Planning extends PdfDocument
{
    /**
     * @param RoundNumber $roundNumber
     * @param string $title
     * @return PagePlanning
     * @throws Zend_Pdf_Exception
     */
    protected function createPagePlanning(RoundNumber $roundNumber, string $title): PagePlanning
    {
        $page = new PagePlanning($this, $this->getPlanningPageDimension($roundNumber));
        $page->setFont($this->getFont(), $this->getFontHeight());
        $page->setTitle($title);
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
        PagePlanning $page,
        float $y,
        bool $recursive
    ): void {
        $y = $page->drawRoundNumberHeader($roundNumber, $y);
        $games = $page->getFilteredGames($roundNumber);
        if (count($games) > 0) {
            $page->initGameLines($roundNumber);
            $y = $page->drawGamesHeader($roundNumber, $y);
        }
        $games = $roundNumber->getGames(GameOrder::ByDate);
        $recessHelper = new RecessHelper($roundNumber);
        $recesses = $recessHelper->getRecesses($this->tournament);
        foreach ($games as $game) {
            $gameHeight = $page->getRowHeight();
            $recessPeriodToDraw = $recessHelper->removeRecessBeforeGame($game, $recesses);
            $gameHeight += $recessPeriodToDraw !== null ? $gameHeight : 0;
            if ($y - $gameHeight < PdfPage::PAGEMARGIN) {
                // $field = $page->getFieldFilter();
                $page = $this->createPagePlanning($roundNumber, $page->getTitle() ?? '');
                $y = $page->drawHeader($page->getTitle(), );
                $page->setGameFilter($page->getGameFilter());
                $page->initGameLines($roundNumber);
                $y = $page->drawGamesHeader($roundNumber, $y);
            }
            if ($recessPeriodToDraw !== null) {
                $page->initGameLines($roundNumber);
                $y = $page->drawRecess($roundNumber, $game, $recessPeriodToDraw, $y);
            }
            $y = $page->drawGame($game, $y);
        }

        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null && $recursive) {
            $y -= 20;
            $this->drawPlanningPerFieldOrPouleHelper($nextRoundNumber, $page, $y, $recursive);
        }
    }
}
