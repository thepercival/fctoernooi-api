<?php

declare(strict_types=1);

namespace App\Export\Pdf\Pages\Traits;

use App\Export\Pdf\Align;
use App\Export\Pdf\Configs\HeaderConfig;
use App\Export\Pdf\Document;
use App\Export\Pdf\Documents;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use App\ImageSize;
use Zend_Pdf_Resource_Image;
use Zend_Pdf_Resource_ImageFactory;
use Zend_Pdf_Exception;

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
        $xLeft = self::PAGEMARGIN;
        $widthAppText = $this->getTextWidth('FCToernooi', $config->getFontHeight());
        $this->setFont($this->helper->getTimesFont(true), $config->getFontHeight());
        $widthSubtitle = strlen($subTitle) > 0 ? $this->getTextWidth($subTitle, $config->getFontHeight()) : 0;
        $this->setFont($this->helper->getTimesFont(), $config->getFontHeight());
        $xCenter = $xLeft + $widthSubtitle + $padding;
        $xRight = $this->getWidth() - (self::PAGEMARGIN + $widthAppText);

        $widthCenter = $displayWidth - ($widthSubtitle + $padding + $padding + $widthAppText);
        $y = $config->getYStart();
        if ($y === null) {
            $y = $this->getHeight() - self::PAGEMARGIN;
        }

        $theme = $this->parent->getTheme();
        $this->setTextColor(new \Zend_Pdf_Color_Html($theme->textColor));
        $this->setFillColor(new \Zend_Pdf_Color_Html($theme->bgColor));

        // draw fill
        {
            $rectangle = new Rectangle(
                new HorizontalLine(new Point($xLeft, $y), $widthSubtitle),
                -$rowHeight
            );
            $arrCorners = [10,0,0,10];
            $this->drawCell($subTitle, $rectangle, Align::Left, $theme->bgColor, $arrCorners);
        }

        // draw tournament name and logo
        {
            $rectangle = new Rectangle(
                new HorizontalLine(new Point($xCenter, $y), $widthCenter),
                -$rowHeight
            );
            $this->drawCell($tournamentName, $rectangle, Align::Center, $theme->bgColor);
        }

        // draw subTitle
        if (strlen($subTitle) > 0) {
            $this->setFont($this->helper->getTimesFont(), $config->getFontHeight());
            $rectangle = new Rectangle(
                new HorizontalLine(new Point($xRight, $y), $widthAppText),
                -$rowHeight
            );
            $arrCorners = [0,10,10,0];
            $this->drawCell('FCToernooi', $rectangle, Align::Right, $theme->bgColor, $arrCorners);
            $this->setFont($this->helper->getTimesFont(), $config->getFontHeight());
        }

        $this->setFillColor(new \Zend_Pdf_Color_Html('white'));
        $this->setTextColor(new \Zend_Pdf_Color_Html('black'));

        return $y - (2 * $rowHeight);
    }
}
