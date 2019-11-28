<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 15:03
 */

namespace App\Export\Excel\Worksheet;

use App\Export\Excel\Spreadsheet;
use FCToernooi\QRService;
use Voetbal\Round;
use Voetbal\Poule;
use Voetbal\NameService;
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

    public function __construct( Spreadsheet $parent = null )
    {
        parent::__construct( $parent, 'qrcode' );
        $parent->addSheet($this, Spreadsheet::INDEX_QRCODE );
        $this->setWidthColumns();
        $this->setCustomHeader();
        $this->qrService = new QRService();
    }

    protected function setWidthColumns()
    {
        $this->getColumnDimensionByColumn( 1 )->setWidth(QRCode::WIDTH_COLUMN_MARGIN);
        $this->getColumnDimensionByColumn( 2 )->setWidth(QRCode::WIDTH_COLUMN_MAIN);
        $this->getColumnDimensionByColumn( 3 )->setWidth(QRCode::WIDTH_COLUMN_MARGIN);
    }

    public function draw()
    {
        $url = "https://www.fctoernooi.nl/" . $this->getParent()->getTournament()->getId();
        $row = 1;
        $row = $this->drawSubHeader( $row, $url, 2, 2 );

        $imgWidth = 300;
        $qrPngPath = $this->qrService->getPngPath( $this->getParent()->getTournament(), $url, $imgWidth );

        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('toernooi qrcode');
        $drawing->setPath($qrPngPath );
        $drawing->setHeight(QRCode::WIDTH_COLUMN_MAIN * 9 );
        $drawing->setWidth( QRCode::WIDTH_COLUMN_MAIN * 9 );
        $drawing->setCoordinates( $this->chr(2, $row) );
        $drawing->setWorksheet($this);
    }
}