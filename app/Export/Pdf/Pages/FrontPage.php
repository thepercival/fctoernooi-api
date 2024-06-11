<?php

declare(strict_types=1);

namespace App\Export\Pdf\Pages;

use App\Export\Pdf\Align;
use App\Export\Pdf\Documents\FrontPageDocument as FrontPageDocument;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use App\ImageProps;
use App\ImageSize;
use Zend_Pdf_Exception;
use Zend_Pdf_Page;
use Zend_Pdf_Resource_Image;
use Zend_Pdf_Resource_ImageFactory;

/**
 * @template-extends ToernooiPdfPage<FrontPageDocument>
 */
class FrontPage extends ToernooiPdfPage
{
    public function __construct(FrontPageDocument $document, mixed $param1)
    {
        parent::__construct($document, $param1);
        $this->setLineWidth(0.5);
        $theme = $this->parent->getTournament()->getTheme();
        if( $theme && array_key_exists('textColor', $theme) ) {
            $this->setTextColor(new \Zend_Pdf_Color_Html($theme['textColor']));
        }
        if( $theme && array_key_exists('bgColor', $theme) ) {
            $this->setFillColor(new \Zend_Pdf_Color_Html($theme['textColor']));
        }

    }

    public function draw(): void
    {
        $logoPath = $this->parent->getTournamentLogoPath(null);

        $config = $this->parent->getConfig();

       $padding = $config->getPadding();

        $rectangleWidth = $this->getWidth() - (2 * $padding);
        $rectangleHeight = $this->getHeight() - (2 * $padding);
        $horLine = new HorizontalLine(new Point($padding, $this->getHeight() - $padding), $rectangleWidth);
        $rectangle = new Rectangle($horLine, -$rectangleHeight);

        // Draw Rectangle
        {
            $lineColors = ['t' => 'black', 'l' => 'black', 'r' => 'black', 'b' => 'black'];
            $this->drawCell('', $rectangle, Align::Center, $lineColors);
        }

        $fontHeight = $config->getFontHeight();

        // Draw Name & Date
        {
            $yText = $rectangle->getTop()->getY() - (0.33 * $rectangleHeight);
            $xStartText = $rectangle->getLeft()->getX();

            $startPointName = new Point($xStartText, $yText);
            $this->setFont($this->helper->getTimesFont(), $fontHeight );
            $this->drawString(
                $this->parent->getTournament()->getName(), $startPointName, $rectangle->getWidth(), Align::Center
            );

            $dateFontHeight = 10;
            $this->setFont($this->helper->getTimesFont(), $dateFontHeight );
            $startPointDate = new Point($xStartText, $yText - (2 * $dateFontHeight));
            $startDateTime = $this->parent->getTournament()->getCompetition()->getStartDateTime();

            $start = strtolower( $this->getDateFormatter('eeee d MMMM y')->format($startDateTime) );
            $this->drawString(
                $start, $startPointDate, $rectangle->getWidth(), Align::Center
            );
        }

        // Draw Logo
        if( $logoPath !== null ) {
            try {
                $logoSize = ImageSize::Normal->value;
                $xCenter = $this->getWidth() / 2;
                $xImage = $xCenter - ($logoSize / 2);
                $yImage = $rectangle->getBottom()->getY() + (0.33 * $rectangleHeight);
                /** @var Zend_Pdf_Resource_Image $img */
                $img = Zend_Pdf_Resource_ImageFactory::factory($logoPath);
                $imgHorLineStart = new HorizontalLine(new Point($xImage, $yImage), $logoSize);
                $imgRectangle = new Rectangle($imgHorLineStart, -$logoSize);
                $this->drawImageExt($img, $imgRectangle);
            } catch ( Zend_Pdf_Exception $e ) {
                // $this->logger->warning($e->getMessage());
            }
        }
    }
}
