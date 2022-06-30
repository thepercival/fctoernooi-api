<?php

declare(strict_types=1);

namespace App\Export\Pdf\Document;

use App\Export\Pdf\Configs\QRCodeConfig;
use App\Export\Pdf\Document as PdfDocument;
use App\Export\Pdf\Page\QRCode as QRCodePage;
use App\Export\PdfProgress;
use FCToernooi\Tournament;
use Sports\Structure;
use Zend_Pdf_Page;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class QRCode extends PdfDocument
{
    public function __construct(
        protected Tournament $tournament,
        protected Structure $structure,
        protected string $url,
        protected PdfProgress $progress,
        protected float $maxSubjectProgress,
        protected QRCodeConfig $config
    ) {
        parent::__construct($tournament, $structure, $url, $progress, $maxSubjectProgress);
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
