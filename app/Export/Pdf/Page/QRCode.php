<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 7-1-19
 * Time: 10:00
 */

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Page as ToernooiPdfPage;
use FCToernooi\QRService;

class QRCode extends ToernooiPdfPage
{
    protected $rowHeight;
    protected $qrService;

    public function __construct( $param1 )
    {
        parent::__construct( $param1 );
        $this->setLineWidth( 0.5 );
        $this->qrService = new QRService();
    }

    public function getPageMargin(){ return 20; }
    public function getHeaderHeight(){ return 0; }

    protected function getRowHeight() {
        if( $this->rowHeight === null ) {
            $this->rowHeight = 18;
        }
        return $this->rowHeight;
    }

    public function draw()
    {
        $nY = $this->drawHeader( "qrcode" );

        $url = "https://www.fctoernooi.nl/" . $this->getParent()->getTournament()->getId();

        $nY = $this->drawSubHeader( $url, $nY );

        $imgWidth = 300;
        $qrPngPath = $this->qrService->getPngPath( $this->getParent()->getTournament(), $url, $imgWidth );
        $img = \Zend_Pdf_Resource_ImageFactory::factory( $qrPngPath );
        $xLeft = $this->getPageMargin() + ( $this->getDisplayWidth() / 2 ) - ( $imgWidth / 2 );
        $this->drawImage( $img, $xLeft, $nY - $imgWidth, $xLeft + $imgWidth, $nY );
    }
}