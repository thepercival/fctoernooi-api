<?php

declare(strict_types=1);

namespace App\Export\Pdf\Document;

use App\Export\Pdf\Configs\Structure\StructureConfig;
use App\Export\Pdf\Document as PdfDocument;
use App\Export\Pdf\Drawers\Structure\CategoryDrawer;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page;
use App\Export\Pdf\Page\Structure as StructurePage;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
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
    private CategoryDrawer $categoryDrawer;

    public function __construct(
        protected Tournament $tournament,
        protected \Sports\Structure $structure,
        protected string $url,
        protected PdfProgress $progress,
        protected float $maxSubjectProgress,
        protected StructureConfig $config
    ) {
        parent::__construct($tournament, $structure, $url, $progress, $maxSubjectProgress);
        $drawCategoryHeader = !$this->getStructure()->hasSingleCategory();
        $this->categoryDrawer = new CategoryDrawer($this->getStructureNameService(), $drawCategoryHeader);
    }

    public function getConfig(): StructureConfig
    {
        return $this->config;
    }

    protected function renderCustom(): void
    {
        $horLine = null;
        $categories = $this->getStructure()->getCategories();
        while ($category = array_shift($categories)) {
            $nextCategory = array_shift($categories);
            if ($horLine === null) {
                $page = $this->createStructurePage($this->calculatePageDimensions($category));
                $y = $page->drawHeader($this->getTournament()->getName(), 'opzet & indeling');
                $horLine = new HorizontalLine(new Point(Page::PAGEMARGIN, $y), $page->getDisplayWidth());
            }
            $rectangle = new Rectangle($horLine, -($horLine->getY() - Page::PAGEMARGIN));
            if ($nextCategory != null) {
                if ($this->canRenderBesideEachOther($category, $nextCategory, $rectangle)) {
                    $bottomRight = $this->categoryDrawer->drawCategory($page, $category, $horLine->getStart(), 1);
                    $topLeftTwo = new Point($bottomRight->getX() + $this->config->getPadding(), $horLine->getY());
                    $bottomRightTwo = $this->categoryDrawer->drawCategory($page, $category, $topLeftTwo, 1);
                    $bottom = $bottomRight->getY() < $bottomRightTwo->getY() ? $bottomRight : $bottomRightTwo->getY();
                    $horLine = new HorizontalLine(
                        new Point(Page::PAGEMARGIN, $bottom->getY()), $page->getDisplayWidth()
                    );
                    continue;
                } else {
                    array_unshift($categories, $category);
                }
            }

            $maxNrOfPouleRows = $this->getLowestNrOfPouleRows($category, $rectangle);
            if ($maxNrOfPouleRows === null) {
                $horLine = null;
                array_unshift($categories, $category);
                continue;
                // will create new page and better dimensions
            }


            $topLeft = $rectangle->getTop()->getStart();
            $bottomRight = $this->categoryDrawer->drawCategory($page, $category, $topLeft, $maxNrOfPouleRows);
            $horLine = new HorizontalLine($bottomRight, $topLeft->getX() - $bottomRight->getX());
            $horLine = $horLine->addY(-$this->getConfig()->getPadding());
        }
    }

    private function canRenderBesideEachOther(Category $category, Category $nextCategory, Rectangle $rectangle): bool
    {
        $categoryOneRectangle = $this->categoryDrawer->calculateRectangle($category, 1, $rectangle->getWidth());
        $categoryTwoRectangle = $this->categoryDrawer->calculateRectangle($nextCategory, 1, $rectangle->getWidth());
        if ($categoryOneRectangle->getHeight() < $rectangle->getHeight()
            && $categoryTwoRectangle->getHeight() < $rectangle->getHeight()
        ) {
            $catOneWidth = $categoryOneRectangle->getWidth();
            $catTwoWidth = $categoryTwoRectangle->getWidth();
            if (($catOneWidth + $this->config->getPadding() + $catTwoWidth) <= $rectangle->getWidth()) {
                return true;
            }
        }
        return false;
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
        $rectangle = $this->categoryDrawer->calculateRectangle($category, $nrOfPouleRows, $outerRectangle->getWidth());
        while ($rectangle->getWidth() > $outerRectangle->getWidth()) {
            $rectangle = $this->categoryDrawer->calculateRectangle(
                $category,
                ++$nrOfPouleRows,
                $outerRectangle->getWidth()
            );
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
        $point = new Point(Page::PAGEMARGIN, Page::A4_PORTRET_HEIGHT - Page::PAGEMARGIN);
        $horLine = new HorizontalLine($point, Page::A4_PORTRET_WIDTH - (2 * Page::PAGEMARGIN));
        $rectangle = new Rectangle($horLine, -(Page::A4_PORTRET_HEIGHT - (2 * Page::PAGEMARGIN)));
        $lowestNrOfPouleRows = $this->getLowestNrOfPouleRows($category, $rectangle);
        if ($lowestNrOfPouleRows !== null) {
            return new Point(Page::A4_PORTRET_WIDTH, Page::A4_PORTRET_HEIGHT);
        }
        $rectangle = $this->categoryDrawer->calculateRectangle($category, 1, $rectangle->getWidth());
        $portretAspectRatio = Page::A4_PORTRET_WIDTH / Page::A4_PORTRET_HEIGHT;
        if ($rectangle->getAspectRatio() > $portretAspectRatio) {
            return new Point($rectangle->getWidth(), $rectangle->getWidth() * $portretAspectRatio);
        }
        return new Point($rectangle->getHeight() / $portretAspectRatio, $rectangle->getHeight());
    }

//    private function calculateRectangle(Category $category, int $maxNrOfPouleRows): Rectangle
//    {
//        $defaultWidth = Page::A4_PORTRET_WIDTH;
//        $minimalWidth = $this->categoryDrawer->getRectangle($category, $maxNrOfPouleRows);
//        return $minimalWidth < $defaultWidth ? $defaultWidth : $minimalWidth;
//    }
}
