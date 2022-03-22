<?php

declare(strict_types=1);

namespace App\Export\Pdf\Document;

use App\Export\Pdf\Document as PdfDocument;
use App\Export\Pdf\Page\QRCode as QRCodePage;
use Zend_Pdf_Page;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class QRCode extends PdfDocument
{
    protected function fillContent(): void
    {
        $page = $this->createPageQRCode();
        $page->draw();
    }

    protected function createPageQRCode(): QRCodePage
    {
        $page = new QRCodePage($this, Zend_Pdf_Page::SIZE_A4);
        $page->setFont($this->getFont(), $this->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }
}
