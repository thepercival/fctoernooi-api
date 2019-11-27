<?php


namespace App\Export\Excel;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet as WorksheetBase;

class Worksheet extends WorksheetBase
{
    const WIDTH = 70;
    const HEIGHT_IN_CELLS = 47;

    protected function chr( int $column, int $row ): string {
        return chr(64 + $column ) . $row;
    }

    protected function setCustomHeader() {
        $header = '&LFCToernooi';
        $header .= '&C' . $this->getParent()->getTournament()->getCompetition()->getName();
        $header .= '&R' . $this->getTitle();
        $this->getHeaderFooter()->setOddHeader($header);
    }

    protected function range( int $columnStart, int $rowStart, int $columnEnd, int $rowEnd ): string {
        return $this->chr( $columnStart, $rowStart ) . ':' . $this->chr( $columnEnd, $rowEnd );
    }

    protected function drawSubHeader( int $rowStart, string $title, int $colStart, int $colEnd, $border = null ): int  {
        $range = $this->range(  $colStart, $rowStart, $colEnd, $rowStart);
        if( $border === true ) {
            $this->border($this->getStyle($range), 'outline' );
        }
        $this->mergeCells( $range );
        $cellHeader = $this->getCellByColumnAndRow($colStart, $rowStart);
        $style = $cellHeader->getStyle();
        $style->getAlignment()->setHorizontal( Alignment::HORIZONTAL_CENTER );

        $cellHeader->setValue( $title );
        return $rowStart + 2;
    }

    public function getParent(): Spreadsheet  {
        /** @var Spreadsheet $parent */
        $parent = parent::getParent();
        return $parent;
    }

    protected function fill( Style $style, string $color ) {
        $styleArray = [
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => [
                    'argb' => $color,
                ],
            ],
        ];
        $style->applyFromArray($styleArray);
        $this->border($style, 'allBorders', 'CCCCCC' );
    }

    protected function border( Style $style, string $borderType, string $color = null ) {
        if( $color === null ) {
            $color = 'black';
        }
        $styleArray = [
            'borders' => array(
                $borderType => array(
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => array('argb' => $color),
                ),
            )
        ];
        $style->applyFromArray($styleArray);
    }
}