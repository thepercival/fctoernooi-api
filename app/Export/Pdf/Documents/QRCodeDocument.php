<?php

declare(strict_types=1);

namespace App\Export\Pdf\Documents;

use App\Export\Pdf\Configs\QRCodeConfig;
use App\Export\Pdf\Document as PdfDocument;
use App\Export\Pdf\Pages\QRCodePage as QRCodePage;
use App\Export\PdfProgress;
use App\ImagePathResolver;
use FCToernooi\Tournament;
use Sports\Structure;
use Zend_Pdf_Page;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class QRCodeDocument extends PdfDocument
{
    public function __construct(
        Tournament $tournament,
        Structure $structure,
        ImagePathResolver $imagePathResolver,
        PdfProgress $progress,
        float $maxSubjectProgress,
        protected QRCodeConfig $config
    ) {
        parent::__construct($tournament, $structure, $imagePathResolver, $progress, $maxSubjectProgress);
    }

    public function getConfig(): QRCodeConfig
    {
        return $this->config;
    }

    protected function renderCustom(): void
    {
        $page = $this->createPageQRCode();
        $page->draw();
    }

    protected function createPageQRCode(): QRCodePage
    {
        $page = new QRCodePage($this, Zend_Pdf_Page::SIZE_A4);
        $page->setFont($this->helper->getTimesFont(), $this->config->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }
}
