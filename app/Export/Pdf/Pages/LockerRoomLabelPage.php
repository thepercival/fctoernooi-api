<?php

declare(strict_types=1);

namespace App\Export\Pdf\Pages;

use App\Export\Pdf\Align;
use App\Export\Pdf\Documents\LockerRoomsDocument as LockerRoomsDocument;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use App\ImageSize;
use FCToernooi\Competitor;
use FCToernooi\LockerRoom as LockerRoomBase;
use FCToernooi\QRService;
use Zend_Pdf_Resource_Image;

/**
 * @template-extends ToernooiPdfPage<LockerRoomsDocument>
 */
class LockerRoomLabelPage extends ToernooiPdfPage
{
    protected QRService $qrService;

    public function __construct(
        LockerRoomsDocument $document,
        mixed $param1,
        protected LockerRoomBase $lockerRoom
    ) {
        parent::__construct($document, $param1);
        // $this->setFont($this->helper->getTimesFont(), $this->config->getFontHeight());

        $this->setLineWidth(0.5);
        $this->qrService = new QRService();
    }

    protected function getCompetitorFontHeight(float $columnWidth): float
    {
        $fontHeight = $this->parent->getLabelConfig()->getStartFontSize();

        $texts = [];
        {
            $texts[] = 'kleedkamer ' . $this->lockerRoom->getName();
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

        while ($fontHeight < $this->parent->getLabelConfig()->getMaxFontSize()
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
        $y = $this->drawHeader($this->parent->getTournament()->getName(), "kleedkamer");
        $y = $this->drawLockerRoom($y);
        $infoHeight = $this->parent->getTournament()->getPublic() ? $this->parent->getLabelConfig()->getInfoHeight(
        ) : 0;
        $this->drawCompetitors($competitors, $y, self::PAGEMARGIN + $infoHeight);
        if ($this->parent->getTournament()->getPublic()) {
            $this->drawInfo();
        }
    }

    protected function drawLockerRoom(float $y): float
    {
        $x = self::PAGEMARGIN;
        $columnWidth = $this->getDisplayWidth();

        $fontHeight = $this->getCompetitorFontHeight($columnWidth);
        $rowHeight = $fontHeight + ((int)(floor($fontHeight / 2)));

        //  $x = $this->getXLineCentered($nrOfPoulesForLine, $pouleWidth, $pouleMargin);
        $this->setFont($this->helper->getTimesFont(true), $fontHeight);
        $this->drawCell(
            "kleedkamer " . $this->lockerRoom->getName(),
            new Rectangle(
                new HorizontalLine(new Point($x, $y,), $columnWidth),
                -$rowHeight
            ),
            Align::Center,
            "black"
        );
        $this->setFont($this->helper->getTimesFont(), $fontHeight);
        return $y - (2 * $rowHeight);
    }

    /**
     * @param list<Competitor> $competitors
     * @param float $yStart
     * @param float $yEnd
     * @throws \Zend_Pdf_Exception
     */
    protected function drawCompetitors(array &$competitors, float $yStart, float $yEnd): void
    {
        $x = self::PAGEMARGIN;
        $columnWidth = $this->getDisplayWidth();

        $fontHeight = $this->getCompetitorFontHeight($columnWidth);
        $rowHeight = $fontHeight + ((int)(floor($fontHeight / 2)));

        $y = $yStart;
        while (($y - $rowHeight) >= $yEnd && $competitor = array_shift($competitors)) {
            $this->drawCell(
                $competitor->getName(),
                new Rectangle(
                    new HorizontalLine(new Point($x, $y,), $columnWidth),
                    $rowHeight
                ),
                Align::Center,
                "black"
            );
            $y -= $rowHeight;
        }
    }

    protected function drawInfo(): void
    {
        $center = $this->getWidth() / 2;
        $infoHeight = $this->parent->getLabelConfig()->getInfoHeight();
        $centerLeft = $center - (self::PAGEMARGIN / 2);

        $this->setFont($this->helper->getTimesFont(), $this->parent->getLabelConfig()->getInfoFontSize());
        $x = self::PAGEMARGIN;
        $maxWidth = (int)($centerLeft - $x);
        $y = self::PAGEMARGIN + ($infoHeight * 2 / 3);
        $this->drawString("toernooi informatie:", new Point($x, $y), $maxWidth, Align::Right);

        $y = self::PAGEMARGIN + ($infoHeight * 1 / 3);
        $url = $this->parent->getWwwUrl() . (string)$this->parent->getTournament()->getId();
        $this->drawString($url, new Point($x, $y), $maxWidth, Align::Right);

        $centerRight = $center + (self::PAGEMARGIN / 2);

        $y = self::PAGEMARGIN + $infoHeight;
        $qrPath = $this->qrService->writeTournamentToJpg($this->parent->getTournament(), $url, $infoHeight);
        /** @var Zend_Pdf_Resource_Image $img */
        $img = \Zend_Pdf_Resource_ImageFactory::factory($qrPath);
        $this->drawImage($img, $centerRight, $y - $infoHeight, $centerRight + $infoHeight, $y);
    }
}
