<?php

declare(strict_types=1);

namespace App\Export\Excel\Worksheet;

use App\Export\Excel\Spreadsheet;
use Sports\Round;
use Sports\Poule;
use Sports\NameService;
use App\Export\Excel\Worksheet as FCToernooiWorksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class Structure extends FCToernooiWorksheet
{
//    const WIDTH_MARGIN_IN_CELLS = 4;
//    const WIDTH_NR_IN_CELLS = 4;
//    const WIDTH_NAME_IN_CELLS = 17;
//    const NR_OF_POULES_PER_LINE = 3;
    const WIDTH_COLUMN = 4;
    const BORDER_COLOR = 'black';

    public function __construct(Spreadsheet $parent = null)
    {
        parent::__construct($parent, 'opzet');
        $parent->addSheet($this, Spreadsheet::INDEX_STRUCTURE);
        $this->setWidthColumns();
        $this->setCustomHeader();
    }

    protected function setWidthColumns()
    {
        for ($column = 1; $column <= $this->getMaxNrOfColumns(); $column++) {
            $this->getColumnDimensionByColumn($column)->setWidth(Structure::WIDTH_COLUMN);
        }
    }

    protected function getMaxNrOfColumns(): int
    {
        return (int)floor(FCToernooiWorksheet::WIDTH / Structure::WIDTH_COLUMN);
    }


    public function draw()
    {
        $rooRound = $this->getParent()->getStructure()->getRootRound();

        $row = 1;
        $column = 1;
        $maxNrOfCells = $this->getMaxNrOfColumns();

        $row = $this->drawSubHeaderHelper($row, "Opzet");
        $this->drawRoundStructure($rooRound, $row, $column, $maxNrOfCells);
    }

    protected function drawSubHeaderHelper(int $rowStart, string $title, int $colStart = null, int $colEnd = null): int
    {
        if ($colStart === null) {
            $colStart = 1;
        }
        if ($colEnd === null) {
            $colEnd = $this->getMaxNrOfColumns();
        }
        return parent::drawSubHeader($rowStart, $title, $colStart, $colEnd);
    }

    protected function drawRoundStructure(Round $round, int $row, int $column, int $maxNrOfCells)
    {
        $startColumn = $column;
        $roundName = $this->getParent()->getNameService()->getRoundName($round);

        $row = $this->drawSubHeader($row, $roundName, $startColumn, ($startColumn + $maxNrOfCells) - 1);

        if ($round->getPoules()->count() === 1 && $round->getPoules()->first()->getPlaces()->count() < 3) {
            return;
        }

        $poules = $round->getPoules()->toArray();
        while (count($poules) > 0) {
            $poulesForLine = $this->getPoulesForLineStructure($poules, $maxNrOfCells);
            $column = $this->getStartColumn($poulesForLine, $startColumn, $maxNrOfCells);
            $maxNrOfPlaceRows = 0;
            while (count($poulesForLine) > 0) {
                $poule = array_shift($poulesForLine);
                $nrOfPouleColumns = $this->getNrOfPouleColumns($poule);

                $this->mergeCells($this->range($column, $row, $column + ($nrOfPouleColumns - 1), $row));
                $cellPoule = $this->getCellByColumnAndRow($column, $row);
                $cellPoule->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $cellPoule->setValue($this->getParent()->getNameService()->getPouleName($poule, false));
                $styleArray = [
                    'borders' => [
                        'outline' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => Indeling::BORDER_COLOR],
                        ],
                    ],
                ];
                $range = $this->range(
                    $column,
                    $cellPoule->getRow(),
                    $column + ($nrOfPouleColumns - 1),
                    $cellPoule->getRow()
                );
                $this->getStyle($range)->applyFromArray($styleArray);


                $places = $poule->getPlaces()->toArray();
                uasort(
                    $places,
                    function ($placeA, $placeB) {
                        return ($placeA->getNumber() > $placeB->getNumber()) ? 1 : -1;
                    }
                );
                $nrOfPlaceRows = count($places) === 3 ? 1 : 2; // bij 3 places, naast elkaar
                $placeColumnDelta = 0;
                foreach ($places as $place) {
                    $placeRowDelta = (($place->getNumber() % 2) === 0) ? 2 : 1;
                    $cellPlace = $this->getCellByColumnAndRow($column + $placeColumnDelta, $row + $placeRowDelta);
                    $cellPlace->setValue($this->getParent()->getNameService()->getPlaceFromName($place, false));
                    $cellPlace->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $range = $this->range(
                        $column + $placeColumnDelta,
                        $cellPlace->getRow(),
                        $column + $placeColumnDelta,
                        $cellPlace->getRow()
                    );
                    $this->border($this->getStyle($range), 'outline');

                    if (($place->getNumber() % 2) === 0) {
                        $placeColumnDelta++;
                    }
                }
                if ($nrOfPlaceRows > $maxNrOfPlaceRows) {
                    $maxNrOfPlaceRows = $nrOfPlaceRows;
                }

                $column += $nrOfPouleColumns + 1;
            }
            $row += 1 + $maxNrOfPlaceRows + 1;
        }

        $nrOfChildren = count($round->getChildren());
        if ($nrOfChildren === 0) {
            return;
        }

        $maxNrOfCellsChildren = (int)floor(($maxNrOfCells - ($nrOfChildren - 1)) / $nrOfChildren);

        $columnChild = $startColumn;
        foreach ($round->getChildren() as $childRound) {
            $this->drawRoundStructure($childRound, $row, $columnChild, $maxNrOfCellsChildren);
            $columnChild += $maxNrOfCellsChildren;
        }
        return;
    }

    protected function getPoulesForLineStructure(array &$poules, int $maxNrOfCells)
    {
        $poulesForLine = [];
        $nrOfCells = 0;
        while ($poule = array_shift($poules)) {
            if ($nrOfCells > 0) {
                $nrOfCells++;
            }
            $nrOfCells += $this->getNrOfPouleColumns($poule);
            if ($nrOfCells > $maxNrOfCells) {
                array_unshift($poules, $poule);
                break;
            }
            $poulesForLine[] = $poule;
        }
        return $poulesForLine;
    }

    protected function getStartColumn(array $poulesForLine, int $startColumn, int $maxNrOfCells): int
    {
        $nrOfPouleCells = count($poulesForLine) - 1; /* margins */
        foreach ($poulesForLine as $poule) {
            $nrOfPouleCells += $this->getNrOfPouleColumns($poule);
        }
        if ($maxNrOfCells < $nrOfPouleCells) {
            return $startColumn;
        }
        $columnDelta = (int)floor(($maxNrOfCells - $nrOfPouleCells) / 2);
        return $startColumn + $columnDelta;
    }

    protected function getNrOfPouleColumns($poule): int
    {
        $nrOfPlaces = $poule->getPlaces()->count();
        if ($nrOfPlaces === 3) {
            $nrOfPlaceColumns = $nrOfPlaces;
        } else {
            $nrOfPlaceColumns = (($nrOfPlaces % 2) === 0 ? $nrOfPlaces : $nrOfPlaces + 1) / 2;
        }
        return $nrOfPlaceColumns;
    }
}
