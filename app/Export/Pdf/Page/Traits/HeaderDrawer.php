<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page\Traits;

use App\Export\Pdf\Align;
use App\Export\Pdf\Configs\HeaderConfig;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use Zend_Pdf_Resource_Image;
use Zend_Pdf_Resource_ImageFactory;

trait HeaderDrawer
{
    public function drawHeader(
        string $tournamentName,
        string $subTitle,
        HeaderConfig $config = null
    ): float {
        if ($config === null) {
            $config = new HeaderConfig(null);
        }
        $this->setFont($this->helper->getTimesFont(), $config->getFontHeight());

        $displayWidth = $this->getDisplayWidth();


        $padding = 10;
        $rowHeight = $config->getRowHeight();
        $imgSize = $rowHeight;
        $xImage = self::PAGEMARGIN;
        $xLeft = $xImage + $imgSize;
        $widthLeft = $this->getTextWidth('FCToernooi', $config->getFontHeight());
        $xCenter = $xLeft + $widthLeft + $padding;
        $this->setFont($this->helper->getTimesFont(true), $config->getFontHeight());
        $widthRight = strlen($subTitle) > 0 ? $this->getTextWidth($subTitle, $config->getFontHeight()) : 0;
        $this->setFont($this->helper->getTimesFont(), $config->getFontHeight());
        $xRight = strlen($subTitle) > 0 ? $this->getWidth() - (self::PAGEMARGIN + $widthRight) : 0;

        $widthCenter = $displayWidth - ($imgSize + $widthLeft + $padding + $padding + $widthRight);
        /** @var Zend_Pdf_Resource_Image $img */
        $img = Zend_Pdf_Resource_ImageFactory::factory(__DIR__ . '/../../../logo.jpg');
        $y = $config->getYStart();
        if ($y === null) {
            $y = $this->getHeight() - self::PAGEMARGIN;
        }

        $imgRectangle = new Rectangle(new HorizontalLine(new Point($xImage, $y), $imgSize), -$imgSize);
        $this->drawImageExt($img, $imgRectangle);

        $arrLineColors = ['b' => 'black'];
        $rectangle = new Rectangle(
            new HorizontalLine(new Point($xLeft, $y), $widthLeft),
            -$rowHeight
        );
        $this->setFillColor(new \Zend_Pdf_Color_Html('#93c54b'));
        $this->drawCell('FCToernooi', $rectangle, Align::Left, $arrLineColors);
        $this->setFillColor(new \Zend_Pdf_Color_Html('white'));

        $rectangle = new Rectangle(
            new HorizontalLine(new Point($xCenter, $y), $widthCenter),
            -$rowHeight
        );
        $this->drawCell($tournamentName, $rectangle, Align::Center, $arrLineColors);

        if (strlen($subTitle) > 0) {
            $this->setFont($this->helper->getTimesFont(true), $config->getFontHeight());
            $rectangle = new Rectangle(
                new HorizontalLine(new Point($xRight, $y), $widthRight),
                -$rowHeight
            );
            $this->drawCell($subTitle, $rectangle, Align::Right, $arrLineColors);
            $this->setFont($this->helper->getTimesFont(), $config->getFontHeight());
        }

        return $y - (2 * $rowHeight);
    }
}
