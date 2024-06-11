<?php

declare(strict_types=1);

namespace App\Export\Pdf\Documents;

use App\Export\Pdf\Configs\RegistrationFormConfig;
use App\Export\Pdf\Document as FCToernooiPdfDocument;
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
class RegistrationFormDocument extends FCToernooiPdfDocument
{
    public function __construct(
        Tournament $tournament,
        protected RegistrationSettings $registrationSettings,
        Structure $structure,
        ImagePathResolver $imagePathResolver,
        PdfProgress $progress,
        float $maxSubjectProgress,
        protected RegistrationFormConfig $config
    ) {
        parent::__construct($tournament, $structure, $imagePathResolver, $progress, $maxSubjectProgress);
    }

    public function getConfig(): RegistrationFormConfig
    {
        return $this->config;
    }

    public function getRegistrationSettings(): RegistrationSettings
    {
        return $this->registrationSettings;
    }

    protected function renderCustom(): void
    {
        $page = $this->createPageRegistrationForm();
        $page->draw();
    }

    protected function createPageRegistrationForm(): RegistrationFormPage
    {
        $page = new RegistrationFormPage($this, Zend_Pdf_Page::SIZE_A4);
        $page->setFont($this->helper->getTimesFont(), $this->config->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }
}
