<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Exceptions\PdfOutOfBoundsException;
use App\Export\Pdf\Document;
use App\Export\Pdf\Page as ToernooiPdfPage;
use FCToernooi\LockerRoom;

class LockerRooms extends ToernooiPdfPage
{
    protected float $rowHeight = 18;

    public function __construct(Document $document, mixed $param1)
    {
        parent::__construct($document, $param1);
        $this->setLineWidth(0.5);
    }

    /**
     * @param list<LockerRoom> $lockerRooms
     * @return int
     */
    protected function getLinesNeeded(array $lockerRooms): int
    {
        $nrOfLines = 0;
        foreach ($lockerRooms as $lockerRoom) {
            $nrOfLines++; // header
            $nrOfLines += $lockerRoom->getCompetitors()->count();
            $nrOfLines++; // between lockerrooms
        }
        $nrOfLines--; // end
        return $nrOfLines;
    }

    protected function getLockerRoomHeight(LockerRoom $lockerRoom): float
    {
        $nrOfLines = 1; // header
        $nrOfLines += $lockerRoom->getCompetitors()->count();
        return $nrOfLines * $this->getRowHeight();
    }

    protected function getLinesAvailable(float $nY): int
    {
        return (int)floor(($nY - $this->getPageMargin()) / $this->getRowHeight());
    }

    protected function getNrOfColumns(float $nY, array $lockerRooms): int
    {
        return (int)ceil($this->getLinesNeeded($lockerRooms) / $this->getLinesAvailable($nY));
    }

    /**
     * @param float $nY
     * @param list<LockerRoom> $lockerRooms
     * @return float
     */
    protected function getColumnWidth(float $nY, array $lockerRooms): float
    {
        $nrOfColumns = $this->getNrOfColumns($nY, $lockerRooms);
        if ($nrOfColumns === 1 || $nrOfColumns === 2) {
            return $this->getMaxColumnWidth();
        }
        return ($this->getDisplayWidth() - (($nrOfColumns - 1) * $this->getPageMargin())) / $nrOfColumns;
    }

    public function getMaxColumnWidth(): float
    {
        return ($this->getDisplayWidth() - $this->getPageMargin()) / 2;
    }

    public function getPageMargin(): float
    {
        return 20;
    }

    public function getHeaderHeight(): float
    {
        return 0;
    }

    protected function getRowHeight(): float
    {
        return $this->rowHeight;
    }

    public function draw(): void
    {
        $nY = $this->drawHeader("kleedkamers");
        $nY = $this->drawSubHeader("Kleedkamer-indeling", $nY);
        $lockerRooms = $this->getParent()->getTournament()->getLockerRooms()->toArray();
        $columnWidth = $this->getColumnWidth($nY, $lockerRooms);
        $nYStart = $nY;
        $nX = $this->getPageMargin();
        while (count($lockerRooms) > 0) {
            $lockerRoom = array_shift($lockerRooms);
            try {
                $nY = $this->drawLockerRoom($lockerRoom, $nX, $nY, $columnWidth);
            } catch (PdfOutOfBoundsException $exception) {
                $nY = $nYStart;
                $nX += $columnWidth + $this->getPageMargin();
                $nY = $this->drawLockerRoom($lockerRoom, $nX, $nY, $columnWidth);
            }
        }
    }

    public function drawLockerRoom(LockerRoom $lockerRoom, float $nX, float $nY, float $columnWidth): float
    {
        if (($nY - $this->getLockerRoomHeight($lockerRoom)) < $this->getPageMargin()) {
            throw new PdfOutOfBoundsException("Y", E_ERROR);
        }
        $nRowHeight = $this->getRowHeight();
        $fontHeight = $nRowHeight - 4;
        //  $nX = $this->getXLineCentered($nrOfPoulesForLine, $pouleWidth, $pouleMargin);
        $this->setFont($this->getParent()->getFont(true), $fontHeight);
        $this->drawCell(
            "kleedkamer " . $lockerRoom->getName(),
            $nX,
            $nY,
            $columnWidth,
            $nRowHeight,
            ToernooiPdfPage::ALIGNCENTER,
            "black"
        );
        $this->setFont($this->getParent()->getFont(), $fontHeight);
        $nY -= $nRowHeight;
        foreach ($lockerRoom->getCompetitors() as $competitor) {
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


//    protected function getXLineCentered($nrOfPoules, $pouleWidth, $margin)
//    {
//        if ($nrOfPoules > $this->maxPoulesPerLine) {
//            $nrOfPoules = $this->maxPoulesPerLine;
//        }
//        $width = ($nrOfPoules * $pouleWidth) + (($nrOfPoules - 1) * $margin);
//        return $this->getPageMargin() + ($this->getDisplayWidth() - $width) / 2;
//    }
}
