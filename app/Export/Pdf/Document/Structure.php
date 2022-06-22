<?php

declare(strict_types=1);

namespace App\Export\Pdf\Document;

use App\Export\Pdf\Configs\Structure\RoundConfig;
use App\Export\Pdf\Document as PdfDocument;
use App\Export\Pdf\Drawers\Structure\CellDrawer;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page as FCToernooiPdfPage;
use App\Export\Pdf\Page\Structure as StructurePage;
use App\Export\Pdf\Point;
use App\Export\PdfProgress;
use FCToernooi\Tournament;
use Sports\Category;

/**
 * PageDimensions counts for all structureCells within Page
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Structure extends PdfDocument
{
    public function __construct(
        protected Tournament $tournament,
        protected \Sports\Structure $structure,
        protected string $url,
        protected PdfProgress $progress,
        protected float $maxSubjectProgress,
        protected RoundConfig $config
    ) {
        parent::__construct($tournament, $structure, $url, $progress, $maxSubjectProgress);
    }

    public function getConfig(): RoundConfig
    {
        return $this->config;
    }

    protected function fillContent(): void
    {
        $horLine = null;
        $cellDrawer = new CellDrawer($this->getStructureNameService());
        foreach ($this->getStructure()->getCategories() as $category) {
            if ($horLine === null) {
                $page = $this->createStructurePage($this->calculatePageDimensions($category));
                $y = $page->drawHeader($this->getTournament()->getName(), 'opzet & indeling');
                $y = $page->drawTitle($category->getName(), $y);
                $horLine = new HorizontalLine(new Point(StructurePage::PAGEMARGIN, $y), $page->getDisplayWidth());
            }
//            $roundNumberAsValue = 1;
//            while ($structureCell = $category->getStructureCellByValue($roundNumberAsValue++)) {
//                if ($horLine === null) {
//                    $page = $this->createStructurePage($this->calculatePageDimensions($category));
//                    $horLine = $page->drawHeader($this->getTournament()->getName(), 'opzet & indeling');
//                }
//                // $horLine = $cellDrawer->draw($page, $structureCell, $horLine);
//            }
//            $rounds = $category->getStructureCell(1)->getRounds();
//
//            $bottom = $roundCardDrawer->drawRoundCardsHorizontal(
//                $page,
//                $rounds
//            );
            // if       bottom - nextstructurecellheight > pagemargin
            // than     createnewpage


//            $y = $page->drawCategory2($category, $this->getStructure()->getFirstRoundNumber());
//            if( $verticalStartLine === null ) {
//
//            }

//            $categoryHeight = $calculator->calculateHeight($category, $dimensions->getX() );
//            if( $page === null || ( $nY - $categoryHeight) > StructurePage::PAGEMARGIN ) {
//                $dimensions = $calculator->getDimensions($category->getRootRound());
//
//                $nY = StructurePage::PAGEMARGIN;
//            }
//            $page->draw($category); // new page when at end
        }
    }

    public function createStructurePage(Point $dimensions): StructurePage
    {
        $page = new StructurePage($this, $dimensions);
        $this->pages[] = $page;
        return $page;
    }

    private function calculatePageDimensions(Category $category): Point
    {
        $minimalWidth = $this->calculateMinimalWidth($category);
        $aspectRatio = FCToernooiPdfPage::A4_PORTRET_HEIGHT / FCToernooiPdfPage::A4_PORTRET_WIDTH;
        return new Point($minimalWidth, $minimalWidth * $aspectRatio);
    }

    private function calculateMinimalWidth(Category $category): float
    {
        $minimalWidth = FCToernooiPdfPage::A4_PORTRET_WIDTH;
        $roundNumberAsValue = 1;
        $cellDrawer = new CellDrawer($this->getStructureNameService());
        while ($structureCell = $category->getStructureCellByValue($roundNumberAsValue++)) {
            $minimalWidthCell = $cellDrawer->getMinimalWidth($structureCell);
            if ($minimalWidthCell > $minimalWidth) {
                $minimalWidth = $minimalWidthCell;
            }
        }
        return $minimalWidth;
    }
}
