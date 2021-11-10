<?php
declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document;
use App\Export\Pdf\Page as ToernooiPdfPage;
use FCToernooi\LockerRoom as LockerRoomBase;
use FCToernooi\QRService;
use Zend_Pdf_Resource_Image;

class LockerRoom extends ToernooiPdfPage
{
    protected float $rowHeight = 18;

    protected QRService $qrService;

    public function __construct(Document $document, mixed $param1, protected LockerRoomBase $lockerRoom)
    {
        parent::__construct($document, $param1);
        $this->setLineWidth(0.5);
        $this->qrService = new QRService();
    }

    public function getPageMargin(): float
    {
        return 20;
    }

    public function getHeaderHeight(): float
    {
        return 0;
    }

    public function getRowHeight(): float
    {
        return $this->rowHeight;
    }

    protected function getCompetitorFontHeight(float $columnWidth): float
    {
        $fontHeight = 40;

        $texts = [];
        {
            $texts[] = "kleedkamer " . $this->lockerRoom->getName();
            foreach ($this->lockerRoom->getCompetitors() as $competitor) {
                $texts[] = $competitor->getName();
            }
        }

        $fncMaxText = function (array $texts) use ($fontHeight) : string {
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

    public function draw(): void
    {
        $y = $this->drawHeader("kleedkamer");
        $y = $this->drawLockerRoom($y);
        if ($this->getParent()->getTournament()->getPublic()) {
            $this->drawInfo($y);
        }
    }

    protected function drawLockerRoom(float $y): float
    {
        $x = $this->getPageMargin();
        $columnWidth = $this->getDisplayWidth();

        $fontHeight = $this->getCompetitorFontHeight($columnWidth);
        $nRowHeight = $fontHeight + ((int)(floor($fontHeight / 2)));

        //  $x = $this->getXLineCentered($nrOfPoulesForLine, $pouleWidth, $pouleMargin);
        $this->setFont($this->getParent()->getFont(true), $fontHeight);
        $this->drawCell(
            "kleedkamer " . $this->lockerRoom->getName(),
            $x,
            $y,
            $columnWidth,
            $nRowHeight,
            Align::Center,
            "black"
        );
        $this->setFont($this->getParent()->getFont(), $fontHeight);
        $y -= $nRowHeight;
        foreach ($this->lockerRoom->getCompetitors() as $competitor) {
            $this->drawCell(
                $competitor->getName(),
                $x,
                $y,
                $columnWidth,
                $nRowHeight,
                Align::Center,
                "black"
            );
            $y -= $nRowHeight;
        }
        return $y - $nRowHeight;
    }

    protected function drawInfo(float $y): void
    {
        $infoHeight = 150;
        if (($y - $infoHeight) < $this->getPageMargin()) {
            return;
        }

        $center = $this->getWidth() / 2;

        $centerLeft = $center - ($this->getPageMargin() / 2);

        $this->setFont($this->getParent()->getFont(), 20);
        $x = $this->getPageMargin();
        $maxWidth = (int)($centerLeft - $x);
        $y = $this->getPageMargin() + $infoHeight * 2 / 3;
        $this->drawString("toernooi informatie:", $x, $y, $maxWidth, Align::Right);

        $y = $this->getPageMargin() + $infoHeight * 1 / 3;
        $url = $this->getParent()->getUrl() . (string)$this->getParent()->getTournament()->getId();
        $this->drawString($url, $x, $y, $maxWidth, Align::Right);

        $centerRight = $center + ($this->getPageMargin() / 2);

        $y = $this->getPageMargin() + $infoHeight;
        $qrPath = $this->qrService->writeTournamentToJpg($this->getParent()->getTournament(), $url, $infoHeight);
        /** @var Zend_Pdf_Resource_Image $img */
        $img = \Zend_Pdf_Resource_ImageFactory::factory($qrPath);
        $this->drawImage($img, $centerRight, $y - $infoHeight, $centerRight + $infoHeight, $y);
    }
}
