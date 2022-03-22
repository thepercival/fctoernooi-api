<?php

declare(strict_types=1);

namespace App\Export\Pdf\Document\Planning;

use App\Export\Pdf\Document\Planning as PdfPlanningDocument;
use App\Export\Pdf\Page\Planning as PlanningPage;
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
}
