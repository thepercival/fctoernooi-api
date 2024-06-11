<?php

declare(strict_types=1);

namespace App\Export\Pdf\Documents;

use App\Export\Pdf\Configs\FrontPageConfig;
use App\Export\Pdf\Configs\RegistrationFormConfig;
use App\Export\Pdf\Document as PdfDocument;
use App\Export\Pdf\Pages\FrontPage;
use App\Export\PdfProgress;
use App\ImagePathResolver;
use FCToernooi\Tournament;
use FCToernooi\Tournament\RegistrationSettings;
use Sports\Structure;
use Zend_Pdf_Page;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class FrontPageDocument extends PdfDocument
{
    public function __construct(
        Tournament $tournament,
        protected Structure $structure,
        ImagePathResolver $imagePathResolver,
        PdfProgress $progress,
        float $maxSubjectProgress,
        protected FrontPageConfig $config
    ) {
        parent::__construct($tournament, $structure, $imagePathResolver, $progress, $maxSubjectProgress);
    }

    public function getConfig(): FrontPageConfig
    {
        return $this->config;
    }

    protected function renderCustom(): void
    {
        $page = $this->createFrontPage();
        $page->draw();
    }

    protected function createFrontPage(): FrontPage
    {
        $page = new FrontPage($this, Zend_Pdf_Page::SIZE_A4);
        $page->setFont($this->helper->getTimesFont(), $this->config->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }
}
