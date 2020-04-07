<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 15:03
 */

namespace App\Export\Pdf\Page;

use App\Exceptions\PdfOutOfBoundsException;
use App\Export\Pdf\Page as ToernooiPdfPage;
use FCToernooi\LockerRoom as LockerRoomBase;
use FCToernooi\QRService;


class LockerRoom extends ToernooiPdfPage
{
    protected $rowHeight;

    /**
     * @var QRService
     */
    protected $qrService;

    /**
     * @var LockerRoomBase
     */
    protected $lockerRoom;

    public function __construct($param1, LockerRoomBase $lockerRoom)
    {
        parent::__construct($param1);
        $this->setLineWidth(0.5);
        $this->lockerRoom = $lockerRoom;
        $this->qrService = new QRService();
    }

    public function getPageMargin()
    {
        return 20;
    }

    public function getHeaderHeight()
    {
        return 0;
    }

    protected function getRowHeight()
    {
        return 18;
    }

    protected function getCompetitorFontHeight($columnWidth)
    {
        $fontHeight = 40;

        $texts = [];
        {
            $texts[] = "kleedkamer " . $this->lockerRoom->getName();
            foreach ($this->lockerRoom->getCompetitors() as $competitor) {
                $texts[] = $competitor->getName();
            }
        }

        $fncMaxText = function ($texts) use ($fontHeight) : string {
            $maxText = null;
            $maxLength = 0;
            foreach ($texts as $text) {
                $textWidth = $this->getTextWidth($text, $fontHeight);
                if ($textWidth > $maxLength) {
                    $maxLength = $textWidth;
                    $maxText = $text;
                }
            }
            return $maxText;
        };
        $maxText = $fncMaxText($texts);

        $maxFontSize = 60;
        while ($fontHeight < $maxFontSize && $this->getTextWidth($maxText, $fontHeight + 1) <= $columnWidth) {
            $fontHeight++;
        }
        return $fontHeight;
    }

    public function draw()
    {
        $nY = $this->drawHeader("kleedkamer");
        $nY = $this->drawLockerRoom($nY);
        if ($this->getParent()->getTournament()->getPublic()) {
            $this->drawInfo($nY);
        }
    }

    protected function drawLockerRoom($nY)
    {
        $nX = $this->getPageMargin();
        $columnWidth = $this->getDisplayWidth();

        $fontHeight = $this->getCompetitorFontHeight($columnWidth);
        $nRowHeight = $fontHeight + ((int)(floor($fontHeight / 2)));

        //  $nX = $this->getXLineCentered($nrOfPoulesForLine, $pouleWidth, $pouleMargin);
        $this->setFont($this->getParent()->getFont(true), $fontHeight);
        $this->drawCell(
            "kleedkamer " . $this->lockerRoom->getName(),
            $nX,
            $nY,
            $columnWidth,
            $nRowHeight,
            ToernooiPdfPage::ALIGNCENTER,
            "black"
        );
        $this->setFont($this->getParent()->getFont(), $fontHeight);
        $nY -= $nRowHeight;
        foreach ($this->lockerRoom->getCompetitors() as $competitor) {
            $this->drawCell(
                $competitor->getName(),
                $nX,
                $nY,
                $columnWidth,
                $nRowHeight,
                ToernooiPdfPage::ALIGNCENTER,
                "black"
            );
            $nY -= $nRowHeight;
        }
        return $nY - $nRowHeight;
    }

    protected function drawInfo($nY)
    {
        $infoHeight = 150;
        if (($nY - $infoHeight) < $this->getPageMargin()) {
            return;
        }

        $center = $this->getWidth() / 2;

        $centerLeft = $center - ($this->getPageMargin() / 2);

        $this->setFont($this->getParent()->getFont(), 20);
        $nX = $this->getPageMargin();
        $maxWidth = (int)($centerLeft - $nX);
        $nY = $this->getPageMargin() + $infoHeight * 2 / 3;
        $this->drawString("toernooi informatie:", $nX, $nY, $maxWidth, ToernooiPdfPage::ALIGNRIGHT);

        $nY = $this->getPageMargin() + $infoHeight * 1 / 3;
        $url = $this->getParent()->getUrl() . $this->getParent()->getTournament()->getId();
        $this->drawString($url, $nX, $nY, $maxWidth, ToernooiPdfPage::ALIGNRIGHT);

        $centerRight = $center + ($this->getPageMargin() / 2);

        $nY = $this->getPageMargin() + $infoHeight;
        $qrPath = $this->qrService->writeToJpg($this->getParent()->getTournament(), $url, $infoHeight);
        $img = \Zend_Pdf_Resource_ImageFactory::factory($qrPath);
        $this->drawImage($img, $centerRight, $nY - $infoHeight, $centerRight + $infoHeight, $nY);
    }

}