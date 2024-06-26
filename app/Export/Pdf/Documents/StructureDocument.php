<?php

declare(strict_types=1);

namespace App\Export\Pdf\Documents;

use App\Export\Pdf\Configs\Structure\StructureConfig;
use App\Export\Pdf\Document as PdfDocument;
use App\Export\Pdf\Drawers\Structure\CategoryDrawer;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Pages\StructurePage as StructurePage;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use App\Export\PdfProgress;
use App\ImagePathResolver;
use App\ImageSize;
use FCToernooi\Tournament;
use Sports\Category;

/**
 * PageDimensions counts for all structureCells within Page
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class StructureDocument extends PdfDocument
{
    private CategoryDrawer $categoryDrawer;

    public function __construct(
        Tournament $tournament,
        \Sports\Structure $structure,
        ImagePathResolver $imagePathResolver,
        PdfProgress $progress,
        float $maxSubjectProgress,
        protected StructureConfig $config
    ) {
        parent::__construct($tournament, $structure, $imagePathResolver, $progress, $maxSubjectProgress);
        $drawCategoryHeader = !$this->getStructure()->hasSingleCategory();
        $this->categoryDrawer = new CategoryDrawer(
            $this->getStructureNameService(),
            $drawCategoryHeader,
            $config->getCategoryConfig()
        );
    }

    public function getConfig(): StructureConfig
    {
        return $this->config;
    }

    protected function renderCustom(): void
    {
        $horLine = null;
        $page = null;
        $categories = $this->getStructure()->getCategories();
        while ($category = array_shift($categories)) {
            $nextCategory = array_shift($categories);
            if ($page === null || $horLine === null) {
                $page = $this->createStructurePage($this->calculatePageDimensions($category));
                $y = $page->drawHeader($this->getTournament()->getName(), 'opzet & indeling');
                $horLine = new HorizontalLine(new Point(ToernooiPdfPage::PAGEMARGIN, $y), $page->getDisplayWidth());
            }
            $rectangle = new Rectangle($horLine, -($horLine->getY() - ToernooiPdfPage::PAGEMARGIN));
            if ($nextCategory != null) {
                if ($this->canRenderBesideEachOther($category, $nextCategory, $rectangle)) {
                    $horLine = $this->renderBesideEachOther($page, $category, $nextCategory, $horLine);
                    continue;
                } else {
                    array_unshift($categories, $nextCategory);
                }
            }

            $maxNrOfPouleRows = $this->getLowestNrOfPouleRows($category, $rectangle);
            if ($maxNrOfPouleRows === null) {
                $horLine = null;
                array_unshift($categories, $category);
                continue;
                // will create new page and better dimensions
            }

            $top = $rectangle->getTop();
            $bottom = $this->categoryDrawer->drawCategory($page, $category, $top, $maxNrOfPouleRows);

            // $horLine = new HorizontalLine($bottomRight, $topLeft->getX() - $bottomRight->getX());
            $horLine = $bottom->addY(-$this->getConfig()->getCategoryConfig()->getMargin());
        }
    }

    private function canRenderBesideEachOther(Category $category, Category $nextCategory, Rectangle $rectangle): bool
    {
        $categoryOneRectangle = $this->categoryDrawer->calculateRectangle($category, 1);
        $categoryTwoRectangle = $this->categoryDrawer->calculateRectangle($nextCategory, 1);
        if ($categoryOneRectangle->getHeight() < $rectangle->getHeight()
            && $categoryTwoRectangle->getHeight() < $rectangle->getHeight()
        ) {
            $catOneWidth = $categoryOneRectangle->getWidth();
            $catTwoWidth = $categoryTwoRectangle->getWidth();
            if (($catOneWidth + $this->config->getCategoryConfig()->getMargin() + $catTwoWidth) <= $rectangle->getWidth(
                )) {
                return true;
            }
        }
        return false;
    }

    private function renderBesideEachOther(
        ToernooiPdfPage $page,
        Category $category,
        Category $nextCategory,
        HorizontalLine $top
    ): HorizontalLine {
        $maxNrOfPouleRows = 1;
        $width = $this->categoryDrawer->calculateRectangle($category, $maxNrOfPouleRows)->getWidth();
        $topLeftCategory = new HorizontalLine($top->getStart(), $width);
        $bottom = $this->categoryDrawer->drawCategory($page, $category, $topLeftCategory, $maxNrOfPouleRows);

        $widthNext = $this->categoryDrawer->calculateRectangle($nextCategory, 1)->getWidth();
        $topLeftNext = new Point($top->getEnd()->getX() - $widthNext, $top->getY());
        $topNext = new HorizontalLine($topLeftNext, $widthNext);
        $bottomNext = $this->categoryDrawer->drawCategory($page, $nextCategory, $topNext, $maxNrOfPouleRows);
        return new HorizontalLine(
            new Point(ToernooiPdfPage::PAGEMARGIN, min($bottom->getY(), $bottomNext->getY())),
            $top->getWidth()
        );
    }


    // draw category
    // width between A4 and bigger
    // bereken de minimale maxNrOfPouleRows binnen A4 breedte,($horLine)
    // als deze niet bestaat bereken de minimale maxNrOfPouleRows op A4-formaat,
    //
    // bereken de minimale maxNrOfPouleRows binnen A4 breedte,($horLine)
    // als deze niet bestaat bereken de minimale maxNrOfPouleRows op A4-formaat,
    private function getLowestNrOfPouleRows(Category $category, Rectangle $outerRectangle): int|null
    {
        $nrOfPouleRows = 1;
        $rectangle = $this->categoryDrawer->calculateRectangle($category, $nrOfPouleRows);
        $previousWidth = null;
        while ($rectangle->getWidth() >= $outerRectangle->getWidth() && $previousWidth !== $rectangle->getWidth()) {
            $previousWidth = $rectangle->getWidth();
            $rectangle = $this->categoryDrawer->calculateRectangle(
                $category,
                ++$nrOfPouleRows
            );
        }
        if ($rectangle->getWidth() > $outerRectangle->getWidth()) {
            return null;
        }
        if ($previousWidth === $rectangle->getWidth()) {
            $nrOfPouleRows--;
        }
        if ($rectangle->getHeight() > $outerRectangle->getHeight()) {
            return null;
        }
        return $nrOfPouleRows;
    }

    public function createStructurePage(Point $dimensions): StructurePage
    {
        $page = new StructurePage($this, $dimensions);
        $this->pages[] = $page;
        return $page;
    }

    private function calculatePageDimensions(Category $category): Point
    {
        $point = new Point(ToernooiPdfPage::PAGEMARGIN, ToernooiPdfPage::A4_PORTRET_HEIGHT - ToernooiPdfPage::PAGEMARGIN);
        $horLine = new HorizontalLine($point, ToernooiPdfPage::A4_PORTRET_WIDTH - (2 * ToernooiPdfPage::PAGEMARGIN));
        $rectangle = new Rectangle($horLine, -(ToernooiPdfPage::A4_PORTRET_HEIGHT - (2 * ToernooiPdfPage::PAGEMARGIN)));
        $lowestNrOfPouleRows = $this->getLowestNrOfPouleRows($category, $rectangle);
        if ($lowestNrOfPouleRows !== null) {
            return new Point(ToernooiPdfPage::A4_PORTRET_WIDTH, ToernooiPdfPage::A4_PORTRET_HEIGHT);
        }
        $rectangle = $this->categoryDrawer->calculateRectangle($category, 1);
        $portretAspectRatio = ToernooiPdfPage::A4_PORTRET_WIDTH / ToernooiPdfPage::A4_PORTRET_HEIGHT;
        if ($rectangle->getAspectRatio() > $portretAspectRatio) {
            $pageWidth = ToernooiPdfPage::PAGEMARGIN + $rectangle->getWidth() + ToernooiPdfPage::PAGEMARGIN;
            return new Point($pageWidth, $pageWidth / $portretAspectRatio);
        }
        $pageHeight = ToernooiPdfPage::PAGEMARGIN + $rectangle->getHeight() + ToernooiPdfPage::PAGEMARGIN;
        return new Point($pageHeight * $portretAspectRatio, $pageHeight);
    }

//    private function calculateRectangle(Category $category, int $maxNrOfPouleRows): Rectangle
//    {
//        $defaultWidth = ToernooiPdfPage::A4_PORTRET_WIDTH;
//        $minimalWidth = $this->categoryDrawer->getRectangle($category, $maxNrOfPouleRows);
//        return $minimalWidth < $defaultWidth ? $defaultWidth : $minimalWidth;
//    }
}
