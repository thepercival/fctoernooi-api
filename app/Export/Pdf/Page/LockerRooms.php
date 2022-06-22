<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document\LockerRooms as LockerRoomsDocument;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use FCToernooi\Competitor;
use FCToernooi\LockerRoom;

/**
 * @template-extends ToernooiPdfPage<LockerRoomsDocument>
 */
class LockerRooms extends ToernooiPdfPage
{
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
        return (1 + $nrOfCompetitors) * $this->parent->getConfig()->getRowHeight();
    }

    protected function getLinesAvailable(float $y): int
    {
        return (int)floor(($y - self::PAGEMARGIN) / $this->parent->getConfig()->getRowHeight());
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
        return ($this->getDisplayWidth() - (($nrOfColumns - 1) * $this->parent->getConfig()->getLockerRoomMargin(
                    ))) / $nrOfColumns;
    }

    public function getMaxColumnWidth(): float
    {
        return ($this->getDisplayWidth() - self::PAGEMARGIN) / 2;
    }

    public function draw(): void
    {
        $y = $this->drawHeader($this->parent->getTournament()->getName(), 'kleedkamers');
        $yStart = $this->drawTitle('indeling kleedkamers', $y);
        $lockerRooms = array_values($this->parent->getTournament()->getLockerRooms()->toArray());
        if (count($lockerRooms) === 0) {
            return;
        }
        $point = new Point(self::PAGEMARGIN, $yStart);
        $columnWidth = $this->getColumnWidth($y, $lockerRooms);
        while (count($lockerRooms) > 0) {
            $lockerRoom = array_shift($lockerRooms);
            $competitors = array_values($lockerRoom->getCompetitors()->toArray());
            while (count($competitors) > 0) {
                $point = $this->drawLockerRoom($lockerRoom, $competitors, $point, $yStart, $columnWidth);
            }
            $point = $point->addY(-$this->parent->getConfig()->getRowHeight());
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
    ): Point
    {
        $rowHeight = $this->parent->getConfig()->getRowHeight();
        $lockerRoomMargin = $this->parent->getConfig()->getLockerRoomMargin();

        $startAtTop = $point->getY() === $yStart;
        $height = $this->getLockerRoomHeight(count($competitors));
        $lockerRoomBeneathPage = ($point->getY() - $height) < self::PAGEMARGIN;
        if ($lockerRoomBeneathPage && !$startAtTop) {
            $newPoint = new Point($point->getX() + $columnWidth + $lockerRoomMargin, $yStart);
            return $this->drawLockerRoom($lockerRoom, $competitors, $newPoint, $yStart, $columnWidth);
        }

        //  $x = $this->getXLineCentered($nrOfPoulesForLine, $pouleWidth, $pouleMargin);
        $this->setFont($this->helper->getTimesFont(true), $this->parent->getConfig()->getFontHeight());
        $this->drawCell(
            'kleedkamer ' . $lockerRoom->getName(),
            new Rectangle(
                new HorizontalLine($point, $columnWidth),
                $rowHeight
            ),
            Align::Center,
            'black'
        );
        $this->setFont($this->helper->getTimesFont(), $rowHeight);
        $point = $point->addY(-$rowHeight);
        while ($competitor = array_shift($competitors)) {
            $this->drawCell(
                $competitor->getName(),
                new Rectangle(
                    new HorizontalLine( $point, $columnWidth),
                    $rowHeight
                ),
                Align::Center,
                'black'
            );
            $point = $point->addY(-$rowHeight);
            if ($lockerRoomBeneathPage && ($point->getY() - $rowHeight) <= self::PAGEMARGIN) {
                return new Point($point->getX() + $columnWidth + $lockerRoomMargin, $yStart);
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
//        return self::PAGEMARGIN + ($this->getDisplayWidth() - $width) / 2;
//    }
}
