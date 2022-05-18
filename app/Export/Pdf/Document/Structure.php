<?php

declare(strict_types=1);

namespace App\Export\Pdf\Document;

use App\Export\Pdf\Document as PdfDocument;
use App\Export\Pdf\Page\Grouping as GroupingPage;
use App\Export\Pdf\Page\Structure as StructurePage;
use App\Export\Pdf\Point;
use App\Export\Pdf\Structure\DimensionCalculator;
use Zend_Pdf_Page;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Structure extends PdfDocument
{
    protected function fillContent(): void
    {
        foreach ($this->getStructure()->getCategories() as $category) {
            $poules = array_values($category->getRootRound()->getPoules()->toArray());
            while (count($poules) > 0) {
                $page = $this->createPageGrouping();
                $page->draw($poules);
            }
            $page = $this->createStructurePage();
            $page->draw();
        }
    }

    protected function createPageGrouping(): GroupingPage
    {
        $page = new GroupingPage($this, Zend_Pdf_Page::SIZE_A4);
        $page->setFont($this->getFont(), $this->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }

    protected function createDefaultStructurePage(): StructurePage
    {
        $page = new StructurePage($this, new Point(0, 0), false);
        $page->setFont($this->getFont(), $this->getFontHeight());
        return $page;
    }

    protected function createStructurePage(bool $enableOutOfBoundsException = true): StructurePage
    {
        $dimensionCalculator = new DimensionCalculator($this->createDefaultStructurePage());
        $maxNrOfPoulePlaceColumns = 1;
        $dimensions = $dimensionCalculator->getDimensions($this->structure, $maxNrOfPoulePlaceColumns);
        $dimensions = $dimensionCalculator->getMinimalDimensions($dimensions);

        $page = new StructurePage($this, $dimensions, $enableOutOfBoundsException);
        $page->setFont($this->getFont(), $this->getFontHeight());
        $this->pages[] = $page;
        $page->setMaxNrOfPoulePlaceColumns($maxNrOfPoulePlaceColumns);
        return $page;
    }
}
