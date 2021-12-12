<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Exceptions\PdfOutOfBoundsException;
use App\Export\Pdf\Align;
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

    protected function getLinesAvailable(float $y): int
    {
        return (int)floor(($y - $this->getPageMargin()) / $this->getRowHeight());
    }

    /**
     * @param float $y
     * @param list<LockerRoom> $lockerRooms
     * @return int
     */
    protected function getNrOfColumns(float $y, array $lockerRooms): int
    {
        return (int)ceil($this->getLinesNeeded($lockerRooms) / $this->getLinesAvailable($y));
    }

    /**
     * @param float $y
     * @param list<LockerRoom> $lockerRooms
     * @return float
     */
    protected function getColumnWidth(float $y, array $lockerRooms): float
    {
        $nrOfColumns = $this->getNrOfColumns($y, $lockerRooms);
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

    public function getRowHeight(): float
    {
        return $this->rowHeight;
    }

    public function draw(): void
    {
        $y = $this->drawHeader('kleedkamers');
        $y = $this->drawSubHeader('Kleedkamer-indeling', $y);
        $lockerRooms = array_values($this->getParent()->getTournament()->getLockerRooms()->toArray());
        if (count($lockerRooms) === 0) {
            return;
        }
        $columnWidth = $this->getColumnWidth($y, $lockerRooms);
        $yStart = $y;
        $x = $this->getPageMargin();
        while (count($lockerRooms) > 0) {
            $lockerRoom = array_shift($lockerRooms);
            try {
                $y = $this->drawLockerRoom($lockerRoom, $x, $y, $columnWidth);
            } catch (PdfOutOfBoundsException $exception) {
                $y = $yStart;
                $x += $columnWidth + $this->getPageMargin();
                $y = $this->drawLockerRoom($lockerRoom, $x, $y, $columnWidth);
            }
        }
    }

    public function drawLockerRoom(LockerRoom $lockerRoom, float $x, float $y, float $columnWidth): float
    {
        if (($y - $this->getLockerRoomHeight($lockerRoom)) < $this->getPageMargin()) {
            throw new PdfOutOfBoundsException('Y', E_ERROR);
        }
        $nRowHeight = $this->getRowHeight();
        $fontHeight = $nRowHeight - 4;
        //  $x = $this->getXLineCentered($nrOfPoulesForLine, $pouleWidth, $pouleMargin);
        $this->setFont($this->getParent()->getFont(true), $fontHeight);
        $this->drawCell(
            'kleedkamer ' . $lockerRoom->getName(),
            $x,
            $y,
            $columnWidth,
            $nRowHeight,
            Align::Center,
            'black'
        );
        $this->setFont($this->getParent()->getFont(), $fontHeight);
        $y -= $nRowHeight;
        foreach ($lockerRoom->getCompetitors() as $competitor) {
            $this->drawCell(
                $competitor->getName(),
                $x,
                $y,
                $columnWidth,
                $nRowHeight,
                Align::Center,
                'black'
            );
            $y -= $nRowHeight;
        }
        return $y - $nRowHeight;
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
