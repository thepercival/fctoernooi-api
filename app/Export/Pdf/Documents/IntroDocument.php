<?php

declare(strict_types=1);

namespace App\Export\Pdf\Documents;

use App\Export\Pdf\Configs\IntroConfig;
use App\Export\Pdf\Document as FCToernooiPdfDocument;
use App\Export\Pdf\Pages\IntroPage;
use App\Export\Pdf\Pages\RegistrationFormPage as RegistrationFormPage;
use App\Export\PdfProgress;
use App\ImagePathResolver;
use FCToernooi\Tournament;
use FCToernooi\Tournament\RegistrationSettings;
use Sports\Structure;
use Zend_Pdf_Page;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class IntroDocument extends FCToernooiPdfDocument
{
    public function __construct(
        Tournament $tournament,
        Structure $structure,
        ImagePathResolver $imagePathResolver,
        PdfProgress $progress,
        float $maxSubjectProgress,
        protected IntroConfig $config
    ) {
        parent::__construct($tournament, $structure, $imagePathResolver, $progress, $maxSubjectProgress);
    }

    public function getConfig(): IntroConfig
    {
        return $this->config;
    }


    protected function renderCustom(): void
    {
        $page = $this->createPageRegistrationForm();
        $page->draw();
    }

    protected function createPageRegistrationForm(): IntroPage
    {
        $page = new IntroPage($this, Zend_Pdf_Page::SIZE_A4);
        $page->setFont($this->helper->getTimesFont(), $this->config->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }
}
