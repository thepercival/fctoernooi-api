<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 15:03
 */

namespace App\Export\Excel\Worksheet;

use App\Export\Excel\Spreadsheet;
use App\Export\Pdf\Page as ToernooiPdfPage;
use Voetbal\Round;
use Voetbal\Poule;
use Voetbal\NameService;
use App\Export\Excel\Worksheet as FCToernooiWorksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class Indeling extends FCToernooiWorksheet
{
    const WIDTH_MARGIN_IN_CELLS = 4;
    const WIDTH_NR_IN_CELLS = 4;
    const WIDTH_NAME_IN_CELLS = 17;
    const NR_OF_POULES_PER_LINE = 3;
    const NR_OF_COLUMNS = 8;
    const BORDER_COLOR = 'black';

    public function __construct( Spreadsheet $parent = null )
    {
        parent::__construct( $parent, 'indeling' );
        $parent->addSheet($this, Spreadsheet::INDEX_STRUCTURE );
        $this->setWidthColumns();
    }

    protected function setWidthColumns()
    {
        $this->getColumnDimensionByColumn( 1 )->setWidth(Indeling::WIDTH_NR_IN_CELLS);
        $this->getColumnDimensionByColumn( 2 )->setWidth(Indeling::WIDTH_NAME_IN_CELLS);

        $this->getColumnDimensionByColumn( 3 )->setWidth(Indeling::WIDTH_MARGIN_IN_CELLS);

        $this->getColumnDimensionByColumn( 4 )->setWidth(Indeling::WIDTH_NR_IN_CELLS);
        $this->getColumnDimensionByColumn( 5 )->setWidth(Indeling::WIDTH_NAME_IN_CELLS);

        $this->getColumnDimensionByColumn( 6 )->setWidth(Indeling::WIDTH_MARGIN_IN_CELLS);

        $this->getColumnDimensionByColumn( 7 )->setWidth(Indeling::WIDTH_NR_IN_CELLS);
        $this->getColumnDimensionByColumn( 8 )->setWidth(Indeling::WIDTH_NAME_IN_CELLS);
    }

    public function draw()
    {
        // $this->setHeaderFooter()
//        $nY = $this->drawHeader( "indeling & structuur" );

        $rooRound = $this->getParent()->getStructure()->getRootRound();

        $row = 1;
        $row = $this->drawSubHeader( $row, "Indeling" );
        $row = $this->drawGrouping( $rooRound, $row  );
    }

    protected function drawSubHeader( int $rowStart, string $title, int $colStart = null, int $colEnd = null ): int  {
        if( $colStart === null ) {
            $colStart = 1;
        }
        if( $colEnd === null ) {
            $colEnd = Indeling::NR_OF_COLUMNS;
        }
        return parent::drawSubHeader( $rowStart, $title, $colStart, $colEnd );
    }

    public function drawGrouping( Round $rootRound, int $rowStart ): int
    {
        $row = $rowStart;
        $nrOnRow = 0;
        foreach( $rootRound->getPoules() as $poule ) {

            $columnStart = ( $nrOnRow * Indeling::NR_OF_POULES_PER_LINE ) + 1;
            $this->mergeCells($this->range(  $columnStart, $row, $columnStart + 1, $row) );

            $nrOnRow = ($poule->getNumber() % Indeling::NR_OF_POULES_PER_LINE );

            $cellPouleName = $this->getCellByColumnAndRow($columnStart, $row);
            $cellPouleName->getStyle()->getAlignment()->setHorizontal( Alignment::HORIZONTAL_CENTER );
            $cellPouleName->setValue( $this->getParent()->getNameService()->getPouleName( $poule, true ) );

            // drawPlaces
            foreach( $poule->getPlaces() as $place ) {
                $this->getCellByColumnAndRow($columnStart, $row + $place->getNumber() )->setValue( $place->getNumber() );
                if ( $place->getCompetitor() !== null ) {
                    $name = $this->getParent()->getNameService()->getPlaceName( $place, true );
                    $this->getCellByColumnAndRow($columnStart + 1, $row + $place->getNumber() )->setValue( $name );
                }
            }

            $styleArray = [
                'borders' => [
                    'vertical' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => Indeling::BORDER_COLOR], // 'FFFF0000'
                    ],
                    'outline' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => Indeling::BORDER_COLOR],
                    ],
                ],
            ];
            $range = $this->range( $columnStart, $row, $columnStart + 1, $row + $poule->getPlaces()->count() );
            $this->getStyle($range)->applyFromArray($styleArray);

            $styleArray = [
                'borders' => [
                    'outline' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => Indeling::BORDER_COLOR],
                    ],
                ],
            ];
            $range = $this->range( $columnStart, $row, $columnStart, $row );
            $this->getStyle($range)->applyFromArray($styleArray);

            if( $nrOnRow === 0 ) {
                $row += 2 + $poule->getPlaces()->count();
            }
        }

        if( $nrOnRow === 0 ) {
            return $row;
        }
        return $row + 2 + $rootRound->getPoule(1)->getPlaces()->count();
    }
}