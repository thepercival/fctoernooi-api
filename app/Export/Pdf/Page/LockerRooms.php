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
use FCToernooi\LockerRoom;

class LockerRooms extends ToernooiPdfPage
{
    protected $rowHeight;

    public function __construct($param1)
    {
        parent::__construct($param1);
        $this->setLineWidth(0.5);
    }

    protected function getLinesNeeded(array $lockerRooms)
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

    protected function getLockerRoomHeight(LockerRoom $lockerRoom)
    {
        $nrOfLines = 1; // header
        $nrOfLines += $lockerRoom->getCompetitors()->count();
        return $nrOfLines * $this->getRowHeight();
    }

    protected function getLinesAvailable($nY): int
    {
        return (int)floor(($nY - $this->getPageMargin()) / $this->getRowHeight());
    }

    protected function getNrOfColumns($nY, array $lockerRooms): int
    {
        return (int)ceil($this->getLinesNeeded($lockerRooms) / $this->getLinesAvailable($nY));
    }

    protected function getColumnWidth($nY, array $lockerRooms): int
    {
        $nrOfColumns = $this->getNrOfColumns($nY, $lockerRooms);
        if ($nrOfColumns === 1 || $nrOfColumns === 2) {
            return $this->getMaxColumnWidth();
        }
        return ($this->getDisplayWidth() - (($nrOfColumns - 1) * $this->getPageMargin())) / $nrOfColumns;
    }

    public function getMaxColumnWidth()
    {
        return ($this->getDisplayWidth() - $this->getPageMargin()) / 2;
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
        if ($this->rowHeight === null) {
            $this->rowHeight = 18;
        }
        return $this->rowHeight;
    }

    public function draw()
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
            } catch (PdfOutOfBoundsException $e) {
                $nY = $nYStart;
                $nX += $columnWidth + $this->getPageMargin();
                $nY = $this->drawLockerRoom($lockerRoom, $nX, $nY, $columnWidth);
            }
        }
    }

    public function drawLockerRoom(LockerRoom $lockerRoom, $nX, $nY, $columnWidth)
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
