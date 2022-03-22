<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document\LockerRooms as LockerRoomsDocument;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Point;
use FCToernooi\Competitor;
use FCToernooi\LockerRoom;

/**
 * @template-extends ToernooiPdfPage<LockerRoomsDocument>
 */
class LockerRooms extends ToernooiPdfPage
{
    private const RowHeight = 18;
    private const FontHeight = self::RowHeight - 4;
    private const LockerRoomMargin = 20;

    public function __construct(LockerRoomsDocument $document, mixed $param1)
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

    protected function getLockerRoomHeight(int $nrOfCompetitors): float
    {
        return (1 + $nrOfCompetitors) * self::RowHeight;
    }

    protected function getLinesAvailable(float $y): int
    {
        return (int)floor(($y - $this->getPageMargin()) / self::RowHeight);
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
        return ($this->getDisplayWidth() - (($nrOfColumns - 1) * self::LockerRoomMargin)) / $nrOfColumns;
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

    public function draw(): void
    {
        $y = $this->drawHeader('kleedkamers');
        $yStart = $this->drawSubHeader('indeling kleedkamers', $y);
        $lockerRooms = array_values($this->parent->getTournament()->getLockerRooms()->toArray());
        if (count($lockerRooms) === 0) {
            return;
        }
        $point = new Point($this->getPageMargin(), $yStart);
        $columnWidth = $this->getColumnWidth($y, $lockerRooms);
        while (count($lockerRooms) > 0) {
            $lockerRoom = array_shift($lockerRooms);
            $competitors = array_values($lockerRoom->getCompetitors()->toArray());
            while (count($competitors) > 0) {
                $point = $this->drawLockerRoom($lockerRoom, $competitors, $point, $yStart, $columnWidth);
            }
            $point = $point->addY(-self::RowHeight);
        }
    }

    /**
     * @param LockerRoom $lockerRoom
     * @param list<Competitor> $competitors
     * @param Point $point
     * @param float $yStart
     * @param float $columnWidth
     * @return Point
     * @throws \Zend_Pdf_Exception
     */
    public function drawLockerRoom(
        LockerRoom $lockerRoom,
        array &$competitors,
        Point $point,
        float $yStart,
        float $columnWidth,
    ): Point {
        $startAtTop = $point->getY() === $yStart;
        $height = $this->getLockerRoomHeight(count($competitors));
        $lockerRoomBeneathPage = ($point->getY() - $height) < $this->getPageMargin();
        if ($lockerRoomBeneathPage && !$startAtTop) {
            $newPoint = new Point($point->getX() + $columnWidth + self::LockerRoomMargin, $yStart);
            return $this->drawLockerRoom($lockerRoom, $competitors, $newPoint, $yStart, $columnWidth);
        }

        //  $x = $this->getXLineCentered($nrOfPoulesForLine, $pouleWidth, $pouleMargin);
        $this->setFont($this->parent->getFont(true), self::FontHeight);
        $this->drawCell(
            'kleedkamer ' . $lockerRoom->getName(),
            $point->getX(),
            $point->getY(),
            $columnWidth,
            self::RowHeight,
            Align::Center,
            'black'
        );
        $this->setFont($this->parent->getFont(), self::FontHeight);
        $point = $point->addY(-self::RowHeight);
        while ($competitor = array_shift($competitors)) {
            $this->drawCell(
                $competitor->getName(),
                $point->getX(),
                $point->getY(),
                $columnWidth,
                self::RowHeight,
                Align::Center,
                'black'
            );
            $point = $point->addY(-self::RowHeight);
            if ($lockerRoomBeneathPage && ($point->getY() - self::RowHeight) <= $this->getPageMargin()) {
                return new Point($point->getX() + $columnWidth + self::LockerRoomMargin, $yStart);
            }
        }
        return $point;
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
