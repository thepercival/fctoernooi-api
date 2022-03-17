<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document;
use App\Export\Pdf\Page as ToernooiPdfPage;
use FCToernooi\Competitor;
use FCToernooi\LockerRoom as LockerRoomBase;
use FCToernooi\QRService;
use Zend_Pdf_Resource_Image;

class LockerRoomLabel extends ToernooiPdfPage
{
    private const InfoHeight = 150;
    private const StartFontSize = 40;
    private const MaxFontSize = 50;
    private const InfoFontSize = 20;

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

    protected function getCompetitorFontHeight(float $columnWidth): float
    {
        $fontHeight = self::StartFontSize;

        $texts = [];
        {
            $texts[] = "kleedkamer " . $this->lockerRoom->getName();
            foreach ($this->lockerRoom->getCompetitors() as $competitor) {
                $texts[] = $competitor->getName();
            }
        }

        $fncMaxText = function (array $texts) use ($fontHeight): string {
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

        while ($fontHeight < self::MaxFontSize
            && $this->getTextWidth($maxText, $fontHeight + 1) <= $columnWidth) {
            $fontHeight++;
        }
        return $fontHeight;
    }

    /**
     * @param list<Competitor> $competitors
     */
    public function draw(array &$competitors): void
    {
        $y = $this->drawHeader("kleedkamer");
        $y = $this->drawLockerRoom($y);
        $infoHeight = $this->getParent()->getTournament()->getPublic() ? self::InfoHeight : 0;
        $this->drawCompetitors($competitors, $y, $this->getPageMargin() + $infoHeight);
        if ($this->getParent()->getTournament()->getPublic()) {
            $this->drawInfo();
        }
    }

    protected function drawLockerRoom(float $y): float
    {
        $x = $this->getPageMargin();
        $columnWidth = $this->getDisplayWidth();

        $fontHeight = $this->getCompetitorFontHeight($columnWidth);
        $rowHeight = $fontHeight + ((int)(floor($fontHeight / 2)));

        //  $x = $this->getXLineCentered($nrOfPoulesForLine, $pouleWidth, $pouleMargin);
        $this->setFont($this->getParent()->getFont(true), $fontHeight);
        $this->drawCell(
            "kleedkamer " . $this->lockerRoom->getName(),
            $x,
            $y,
            $columnWidth,
            $rowHeight,
            Align::Center,
            "black"
        );
        $this->setFont($this->getParent()->getFont(), $fontHeight);
        return $y - $rowHeight;
    }

    /**
     * @param list<Competitor> $competitors
     * @param float $yStart
     * @param float $yEnd
     * @throws \Zend_Pdf_Exception
     */
    protected function drawCompetitors(array &$competitors, float $yStart, float $yEnd): void
    {
        $x = $this->getPageMargin();
        $columnWidth = $this->getDisplayWidth();

        $fontHeight = $this->getCompetitorFontHeight($columnWidth);
        $rowHeight = $fontHeight + ((int)(floor($fontHeight / 2)));

        $y = $yStart;
        while (($y - $rowHeight) >= $yEnd && $competitor = array_shift($competitors)) {
            $this->drawCell(
                $competitor->getName(),
                $x,
                $y,
                $columnWidth,
                $rowHeight,
                Align::Center,
                "black"
            );
            $y -= $rowHeight;
        }
    }

    protected function drawInfo(): void
    {
        $center = $this->getWidth() / 2;

        $centerLeft = $center - ($this->getPageMargin() / 2);

        $this->setFont($this->getParent()->getFont(), self::InfoFontSize);
        $x = $this->getPageMargin();
        $maxWidth = (int)($centerLeft - $x);
        $y = $this->getPageMargin() + (self::InfoHeight * 2 / 3);
        $this->drawString("toernooi informatie:", $x, $y, $maxWidth, Align::Right);

        $y = $this->getPageMargin() + (self::InfoHeight * 1 / 3);
        $url = $this->getParent()->getUrl() . (string)$this->getParent()->getTournament()->getId();
        $this->drawString($url, $x, $y, $maxWidth, Align::Right);

        $centerRight = $center + ($this->getPageMargin() / 2);

        $y = $this->getPageMargin() + self::InfoHeight;
        $qrPath = $this->qrService->writeTournamentToJpg($this->getParent()->getTournament(), $url, self::InfoHeight);
        /** @var Zend_Pdf_Resource_Image $img */
        $img = \Zend_Pdf_Resource_ImageFactory::factory($qrPath);
        $this->drawImage($img, $centerRight, $y - self::InfoHeight, $centerRight + self::InfoHeight, $y);
    }
}
