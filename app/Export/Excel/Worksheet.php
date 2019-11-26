<?php


namespace App\Export\Excel;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet as WorksheetBase;

class Worksheet extends WorksheetBase
{
    const WIDTH = 70;
    const HEIGHT_IN_CELLS = 47;

    protected function chr( int $column, int $row ): string {
        return chr(64 + $column ) . $row;
    }

    protected function range( int $columnStart, int $rowStart, int $columnEnd, int $rowEnd ): string {
        return $this->chr( $columnStart, $rowStart ) . ':' . $this->chr( $columnEnd, $rowEnd );
    }

    protected function drawSubHeader( int $rowStart, string $title, int $colStart, int $colEnd ): int  {
        $range = $this->range(  $colStart, $rowStart, $colEnd, $rowStart);
        $this->mergeCells( $range );
        $cellHeader = $this->getCellByColumnAndRow($colStart, $rowStart);
        $cellHeader->getStyle()->getAlignment()->setHorizontal( Alignment::HORIZONTAL_CENTER );
        $cellHeader->setValue( $title );
        return $rowStart + 2;
    }

    public function getParent(): Spreadsheet  {
        /** @var Spreadsheet $parent */
        $parent = parent::getParent();
        return $parent;
    }
}