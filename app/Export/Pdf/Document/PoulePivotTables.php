<?php

declare(strict_types=1);

namespace App\Export\Pdf\Document;

use App\Export\Pdf\Configs\PoulePivotConfig;
use App\Export\Pdf\Document as PdfDocument;
use App\Export\Pdf\Page\PoulePivotTable\Against as AgainstPoulePivotTablePage;
use App\Export\Pdf\Page\PoulePivotTable\Multiple as MultipleSportsPoulePivotTablePage;
use App\Export\Pdf\Page\PoulePivotTable\Together as TogetherPoulePivotTablePage;
use App\Export\PdfProgress;
use App\ImagePathResolver;
use App\ImageSize;
use FCToernooi\Tournament;
use Sports\Round\Number as RoundNumber;
use Sports\Structure;
use SportsHelpers\Sport\Variant\Against as AgainstSportVariant;
use SportsHelpers\Sport\Variant\AllInOneGame as AllInOneGameSportVariant;
use SportsHelpers\Sport\Variant\Single as SingleSportVariant;
use Zend_Pdf_Page;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class PoulePivotTables extends PdfDocument
{
    public function __construct(
        Tournament $tournament,
        Structure $structure,
        ImagePathResolver $imagePathResolver,
        PdfProgress $progress,
        float $maxSubjectProgress,
        protected PoulePivotConfig $config
    ) {
        parent::__construct($tournament, $structure, $imagePathResolver, $progress, $maxSubjectProgress);
    }

    public function getConfig(): PoulePivotConfig
    {
        return $this->config;
    }

    protected function renderCustom(): void
    {
        $this->drawPoulePivotTables($this->structure->getFirstRoundNumber());
    }

    protected function drawPoulePivotTables(
        RoundNumber $roundNumber,
        AgainstPoulePivotTablePage|TogetherPoulePivotTablePage $page = null,
        float $y = null
    ): void {
        if ($this->someStructureCellNeedsRanking($roundNumber)) {
            if ($roundNumber->getCompetition()->hasMultipleSports()) {
                $this->drawPoulePivotTablesMultipleSports($roundNumber);
            }
            $logoPath = $this->getTournamentLogoPath(ImageSize::Small);
            $biggestPoule = $roundNumber->createPouleStructure()->getBiggestPoule();
            $gameAmountConfigs = $roundNumber->getValidGameAmountConfigs();
            foreach ($gameAmountConfigs as $gameAmountConfig) {
                $page = $this->createPagePoulePivotTables($gameAmountConfig->createVariant(), $biggestPoule);
                $y = $page->drawHeader($this->getTournament()->getName(), $logoPath,  'pouledraaitabel');
                $y = $page->drawPageStartHeader($roundNumber, $gameAmountConfig->getCompetitionSport(), $y);
                foreach ($roundNumber->getRounds() as $round) {
                    foreach ($round->getPoules() as $poule) {
                        if (!$poule->needsRanking()) {
                            continue;
                        }
                        $pouleHeight = $page->getPouleHeight($poule);
                        if ($y - $pouleHeight < AgainstPoulePivotTablePage::PAGEMARGIN) {
                            $nrOfPlaces = $poule->getPlaces()->count();
                            $page = $this->createPagePoulePivotTables($gameAmountConfig->createVariant(), $nrOfPlaces);
                            $y = $page->drawHeader($this->getTournament()->getName(), $logoPath, 'pouledraaitabel');
                            $y = $page->drawPageStartHeader($roundNumber, $gameAmountConfig->getCompetitionSport(), $y);
                        }
                        $y = $page->draw($poule, $gameAmountConfig, $y);
                    }
                }
            }
        }
        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $this->drawPoulePivotTables($nextRoundNumber, $page, $y);
        }
    }

    protected function someStructureCellNeedsRanking(RoundNumber $roundNumber): bool
    {
        foreach ($roundNumber->getStructureCells() as $cell) {
            if ($cell->needsRanking()) {
                return true;
            }
        }
        return false;
    }

    protected function drawPoulePivotTablesMultipleSports(RoundNumber $roundNumber): void
    {
        if (!$this->someStructureCellNeedsRanking($roundNumber)) {
            return;
        }
        $logoPath = $this->getTournamentLogoPath(ImageSize::Small);
        $biggestPoule = $roundNumber->createPouleStructure()->getBiggestPoule();
        $page = $this->createPagePoulePivotTablesMultipleSports($biggestPoule);
        $y = $page->drawHeader($this->getTournament()->getName(), $logoPath, 'pouledraaitabel');
        $y = $page->drawPageStartHeader($roundNumber, $y);

        foreach ($roundNumber->getRounds() as $round) {
            foreach ($round->getPoules() as $poule) {
                if (!$poule->needsRanking()) {
                    continue;
                }
                $pouleHeight = $page->getPouleHeight($poule);
                if ($y - $pouleHeight < MultipleSportsPoulePivotTablePage::PAGEMARGIN) {
                    $nrOfPlaces = $poule->getPlaces()->count();
                    $page = $this->createPagePoulePivotTablesMultipleSports($nrOfPlaces);
                    $y = $page->drawHeader($this->getTournament()->getName(), $logoPath,  'pouledraaitabel');
                    $y = $page->drawPageStartHeader($roundNumber, $y);
                }
                $y = $page->draw($poule, $y);
            }
        }
    }



    /**
     * @param int $maxNrOfPlaces
     * @return MultipleSportsPoulePivotTablePage
     */
    protected function createPagePoulePivotTablesMultipleSports(
        int $maxNrOfPlaces
    ): MultipleSportsPoulePivotTablePage {
        $dimension = $this->getPoulePivotPageDimension(null, $maxNrOfPlaces);
        $page = new MultipleSportsPoulePivotTablePage($this, $dimension);

        $this->pages[] = $page;
        return $page;
    }

    /**
     * @param SingleSportVariant|AgainstSportVariant|AllInOneGameSportVariant $sportVariant
     * @param int $maxNrOfPlaces
     * @return AgainstPoulePivotTablePage|TogetherPoulePivotTablePage
     */
    protected function createPagePoulePivotTables(
        SingleSportVariant|AgainstSportVariant|AllInOneGameSportVariant $sportVariant,
        int $maxNrOfPlaces
    ): AgainstPoulePivotTablePage | TogetherPoulePivotTablePage {
        $dimension = $this->getPoulePivotPageDimension($sportVariant, $maxNrOfPlaces);
        if ($sportVariant instanceof AgainstSportVariant) {
            $page = new AgainstPoulePivotTablePage($this, $dimension);
        } else {
            $page = new TogetherPoulePivotTablePage($this, $dimension);
        }
        $this->pages[] = $page;
        return $page;
    }

    protected function getPoulePivotPageDimension(
        SingleSportVariant|AgainstSportVariant|AllInOneGameSportVariant|null $sportVariant,
        int $maxNrOfPlaces
    ): string {
        if ($sportVariant === null || $sportVariant instanceof AgainstSportVariant) {
            $nrOfColumns = $maxNrOfPlaces;
        } else {
            $nrOfColumns = $sportVariant->getNrOfGamesPerPlace();
        }
        return $nrOfColumns <= 12 ? Zend_Pdf_Page::SIZE_A4 : Zend_Pdf_Page::SIZE_A4_LANDSCAPE;
    }
}
