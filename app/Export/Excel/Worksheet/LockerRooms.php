<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 15:03
 */

namespace App\Export\Excel\Worksheet;

use App\Export\Excel\Spreadsheet;
use App\Export\Excel\Worksheet as WorksheetBase;
use FCToernooi\LockerRoom;
use App\Export\Excel\Worksheet as FCToernooiWorksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class LockerRooms extends FCToernooiWorksheet
{
    public function __construct(Spreadsheet $parent = null)
    {
        parent::__construct($parent, 'kleedkamers');
        $parent->addSheet($this, Spreadsheet::INDEX_LOCKERROOMS);

        $this->setWidthColumns();
        $this->setCustomHeader();
    }

    protected function setWidthColumns()
    {
        $this->getColumnDimensionByColumn(1)->setWidth(20);
        $this->getColumnDimensionByColumn(2)->setWidth(5);
        $this->getColumnDimensionByColumn(3)->setWidth(20);
        $this->getColumnDimensionByColumn(4)->setWidth(5);
        $this->getColumnDimensionByColumn(5)->setWidth(20);
    }

    protected function getMaxNrOfColumns(): int
    {
        return 5;
    }

    public function draw()
    {
        $row = $this->drawLockerRooms();

        $row += (WorksheetBase::HEIGHT_IN_CELLS - ($row % WorksheetBase::HEIGHT_IN_CELLS)) + 1;
        $lockerRooms = $this->getParent()->getTournament()->getLockerRooms()->toArray();
        foreach ($lockerRooms as $lockerRoom) {
            $this->drawLockerRoom($lockerRoom, $row);
            $row += WorksheetBase::HEIGHT_IN_CELLS;
        }
    }

    public function drawLockerRooms()
    {
        $row = 1;
        $col = 1;
        $lockerRooms = $this->getParent()->getTournament()->getLockerRooms()->toArray();
        $maxNrOfRows = 0;
        foreach ($lockerRooms as $lockerRoom) {
            $nrOfRows = $this->drawLockerRoomsHelper($lockerRoom, $row, $col);
            if ($nrOfRows > $maxNrOfRows) {
                $maxNrOfRows = $nrOfRows;
            }
            if ($col < 5) {
                $col += 2;
            } else {
                $row += $maxNrOfRows + 1;
                $col = 1;
            }
        }
        return $row;
    }

    public function drawLockerRoomsHelper(LockerRoom $lockerRoom, int $row, int $col): int
    {
        $range = $this->range($col, $row, $col, $row);
        $this->border($this->getStyle($range), 'outline');

        $cell = $this->getCellByColumnAndRow($col, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $cell->setValue("kleedkamer " . $lockerRoom->getName());

        $startRow = ++$row;
        foreach ($lockerRoom->getCompetitors() as $competitor) {
            $cell = $this->getCellByColumnAndRow($col, $row++);
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $cell->setValue($competitor->getName());
        }
        $range = $this->range($col, $startRow, $col, $row - 1);
        $this->border($this->getStyle($range), 'outline');

        return $lockerRoom->getCompetitors()->count() + 1;
    }

    public function drawLockerRoom(LockerRoom $lockerRoom, int $row): int
    {
        $startCol = 1;
        $startRow = $row;
        $endCol = $this->getMaxNrOfColumns();
        $rowsPerLine = 5;

        $this->mergeCells($this->range($startCol, $row, $endCol, $row + $rowsPerLine));

        $range = $this->range($startCol, $row, $endCol, $row);
        $this->border($this->getStyle($range), 'outline');
        $this->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $this->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->getStyle($range)->getFont()->setBold(true);
        $this->getStyle($range)->getFont()->setSize(25);
        $cell = $this->getCellByColumnAndRow($startCol, $row);
        $cell->setValue("kleedkamer " . $lockerRoom->getName());

        $competitorStartRow = $row + $rowsPerLine + 1;
        foreach ($lockerRoom->getCompetitors() as $competitor) {
            $row += $rowsPerLine + 1;

            $this->mergeCells($this->range($startCol, $row, $endCol, $row + $rowsPerLine));

            $range = $this->range($startCol, $row, $endCol, $row);
            $this->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $this->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->getStyle($range)->getFont()->setSize(25);
            $cell = $this->getCellByColumnAndRow($startCol, $row);
            $cell->setValue($competitor->getName());
        }
        $endRow = $row + $rowsPerLine;
        $range = $this->range($startCol, $competitorStartRow, $endCol, $endRow);
        $this->border($this->getStyle($range), 'outline');

        return $endRow - $startRow;
    }
}