<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Document;
use App\Export\Pdf\Page as ToernooiPdfPage;
use FCToernooi\QRService;
use Zend_Pdf_Resource_Image;

class QRCode extends ToernooiPdfPage
{
    protected float $rowHeight = 18;
    protected QRService $qrService;

    public function __construct(Document $document, mixed $param1)
    {
        parent::__construct($document, $param1);
        $this->setLineWidth(0.5);
        $this->qrService = new QRService();
    }

    public function getPageMargin(): float
    {
        return 20;
    }

    public function getHeaderHeight(): float
    {
        return 0;
    }

    public function getRowHeight(): float
    {
        return $this->rowHeight;
    }

    public function draw(): void
    {
        $y = $this->drawHeader("qrcode");

        $url = $this->getParent()->getUrl() . (string)$this->getParent()->getTournament()->getId();

        $y = $this->drawSubHeader($url, $y);

        $imgWidth = 300;
        $qrPath = $this->qrService->writeTournamentToJpg($this->getParent()->getTournament(), $url, $imgWidth);
        /** @var Zend_Pdf_Resource_Image $img */
        $img = \Zend_Pdf_Resource_ImageFactory::factory($qrPath);
        $xLeft = $this->getPageMargin() + ($this->getDisplayWidth() / 2) - ($imgWidth / 2);
        $this->drawImage($img, $xLeft, $y - $imgWidth, $xLeft + $imgWidth, $y);
    }
}
