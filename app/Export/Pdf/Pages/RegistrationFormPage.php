<?php

declare(strict_types=1);

namespace App\Export\Pdf\Pages;

use App\Export\Pdf\Align;
use App\Export\Pdf\Configs\HeaderConfig;
use App\Export\Pdf\Documents\RegistrationFormDocument as RegistrationFormDocument;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Pages;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use App\ImageProps;
use App\ImageSize;
use FCToernooi\QRService;
use Zend_Pdf_Page;
use Zend_Pdf_Resource_Image;

/**
 * @template-extends ToernooiPdfPage<RegistrationFormDocument>
 */
class RegistrationFormPage extends ToernooiPdfPage
{
    public function __construct(RegistrationFormDocument $document, mixed $param1)
    {
        parent::__construct($document, $param1);
        $this->setLineWidth(0.5);
    }

    public function draw(): void
    {
        $logoPath = $this->parent->getTournamentLogoPath(ImageSize::Small );
        $y = $this->drawHeader(
            $this->parent->getTournament()->getName(),
            $logoPath,
            'inschrijfformulier',
            new HeaderConfig()
        );

        $config = $this->parent->getConfig();
        $rowHeight = $config->getRowHeight() * 2;
        $y -= $rowHeight;
        $xStart = ToernooiPdfPage::PAGEMARGIN;
        $labelWidth = 100;
        $marginStartDashedLine = 30;
        $widthDashedLine = $this->getDisplayWidth() - ($labelWidth + $marginStartDashedLine);

        $xStartDashedLine = $xStart + $labelWidth + $marginStartDashedLine;

        // START FORM
        $this->setFont($this->helper->getTimesFont(), $config->getFontHeight() );
        foreach(['naam','emailadres','telefoon'] as $label) {
            $rectangle = new Rectangle(new HorizontalLine(new Point($xStart, $y), $labelWidth), -$rowHeight);
            $this->drawCell($label . ':', $rectangle);

            // DASHED LINE
            $this->drawDashedLine(new HorizontalLine(new Point($xStartDashedLine, $y - $rowHeight), $widthDashedLine));

            $y -= $rowHeight;
        }

        $y -= $rowHeight;

        // SHOW INFO FROM ORGANIZER
        $settings = $this->parent->getRegistrationSettings();
        $lines = explode(PHP_EOL, $settings->getRemark());
        $rectangle = new Rectangle(new HorizontalLine(new Point($xStartDashedLine, $y), $widthDashedLine), -($rowHeight * (count($lines) + 1 ) ) );
        $this->drawCell( ' ', $rectangle, Align::Left, 'gray');

        $textPaddingX = 5;
        foreach($lines as $line) {
            // DASHED LINE
            $xStartDashedLine = $xStart + $labelWidth + $marginStartDashedLine + $textPaddingX;
            $this->drawString($line, new Point($xStartDashedLine, $y - $rowHeight), $widthDashedLine - (2 * $textPaddingX));

            $y -= $rowHeight;
        }
        $y -= $rowHeight;
        $y -= $rowHeight;

        // LAST PART FORM
        $rectangle = new Rectangle(new HorizontalLine(new Point($xStart, $y), $labelWidth), -$rowHeight);
        $this->drawCell('extra info:', $rectangle);

        // DASHED LINE
        for ( $i = 1 ; $i <= 4 ; $i++) {
            $xStartDashedLine = $xStart + $labelWidth + $marginStartDashedLine;
            $this->drawDashedLine(new HorizontalLine(new Point($xStartDashedLine, $y - $rowHeight), $widthDashedLine));
            $y -= $rowHeight;
        }
    }
}
