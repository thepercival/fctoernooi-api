<?php

declare(strict_types=1);

namespace App\Export\Excel\Worksheet;

use App\Export\Excel\Spreadsheet;
use FCToernooi\QRService;
use Sports\Round;
use Sports\Poule;
use Sports\NameService;
use App\Export\Excel\Worksheet as FCToernooiWorksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class QRCode extends FCToernooiWorksheet
{
    const WIDTH_COLUMN_MARGIN = 10;
    const WIDTH_COLUMN_MAIN = 50;

    /**
     * @var QRService
     */
    protected $qrService;

    public function __construct(Spreadsheet $parent = null)
    {
        parent::__construct($parent, 'qrcode');
        $parent->addSheet($this, Spreadsheet::INDEX_QRCODE);
        $this->setWidthColumns();
        $this->setCustomHeader();
        $this->qrService = new QRService();
    }

    protected function setWidthColumns()
    {
        $this->getColumnDimensionByColumn(1)->setWidth(QRCode::WIDTH_COLUMN_MARGIN);
        $this->getColumnDimensionByColumn(2)->setWidth(QRCode::WIDTH_COLUMN_MAIN);
        $this->getColumnDimensionByColumn(3)->setWidth(QRCode::WIDTH_COLUMN_MARGIN);
    }

    public function draw()
    {
        $url = $this->getParent()->getUrl() . $this->getParent()->getTournament()->getId();
        $row = 1;
        $row = $this->drawSubHeader($row, $url, 2, 2);

        $imgWidth = 300;
        $qrPath = $this->qrService->writeTournamentToJpg($this->getParent()->getTournament(), $url, $imgWidth);

        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('toernooi qrcode');
        $drawing->setPath($qrPath);
        $drawing->setHeight(QRCode::WIDTH_COLUMN_MAIN * 9);
        $drawing->setWidth(QRCode::WIDTH_COLUMN_MAIN * 9);
        $drawing->setCoordinates($this->chr(2, $row));
        $drawing->setWorksheet($this);
    }
}
