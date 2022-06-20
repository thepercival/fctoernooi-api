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
            $config = new HeaderConfig();
        }
        $this->setFont($this->helper->getTimesFont(), $config->getFontHeight());

        $displayWidth = $this->getDisplayWidth();
        $margin = $displayWidth / 25;
        $rowHeight = $config->getRowHeight();
        $imgSize = $rowHeight;
        $widthLeft = $imgSize + $this->getTextWidth('FCToernooi', $config->getFontHeight());
        $xLeft = self::PAGEMARGIN;
        $xCenter = $xLeft + $widthLeft + $margin;
        $widthRight = strlen($subTitle) > 0 ? $this->getTextWidth($subTitle, $config->getFontHeight()) : 0;
        $xRight = strlen($subTitle) > 0 ? $this->getWidth() - (self::PAGEMARGIN + $widthRight) : 0;
        $widthCenter = $displayWidth - ($widthLeft + $margin);
        if (strlen($subTitle) > 0) {
            $widthCenter -= ($margin + $widthRight);
        }
        /** @var Zend_Pdf_Resource_Image $img */
        $img = Zend_Pdf_Resource_ImageFactory::factory(__DIR__ . '/../../../../logo.jpg');
        $y = $this->getHeight() - self::PAGEMARGIN;
        $this->drawImage($img, $xLeft, $y - $imgSize, $xLeft + $imgSize, $y);

        $arrLineColors = ['b' => 'black'];
        $this->drawCell(
            'FCToernooi',
            new Rectangle(
                new HorizontalLine(
                    new Point($xLeft + $imgSize, $y),
                    $widthLeft - $imgSize
                ),
                $rowHeight
            ),
            Align::Left,
            $arrLineColors
        );

        $rectangle = new Rectangle(
            new HorizontalLine(
                new Point($xLeft + $imgSize, $y),
                $widthCenter
            ),
            $rowHeight
        );
        $this->drawCell($tournamentName, $rectangle, Align::Left, $arrLineColors);

        if (strlen($subTitle) > 0) {
            $rectangle = new Rectangle(
                new HorizontalLine(
                    new Point($xRight , $y),
                    $widthRight
                ),
                $rowHeight
            );
            $this->drawCell($subTitle, $rectangle, Align::Right, $arrLineColors);
        }

        return $y - (2 * $rowHeight);
    }
}
