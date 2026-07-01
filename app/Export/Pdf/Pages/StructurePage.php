<?php

declare(strict_types=1);

namespace App\Export\Pdf\Pages;

use App\Export\Pdf\Documents\StructureDocument;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Point;

/**
 * @template-extends ToernooiPdfPage<StructureDocument>
 */
final class StructurePage extends ToernooiPdfPage
{
    private const int ROUNDMARGIN = 10;

//    private int $maxNrOfPoulePlaceColumns = 1;

    public function __construct(StructureDocument $document, Point $point)
    {
        $dimensions = ((string)$point->getX()) . ':' . ((string)$point->getY());
        parent::__construct($document, $dimensions);
        $this->setLineWidth(0.5);
    }
}
