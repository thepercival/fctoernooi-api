<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\Structure;

use App\Export\Pdf\Configs\Structure\RoundConfig;
use App\Export\Pdf\Configs\StructureConfig;
use App\Export\Pdf\Drawers\Helper;
use Sports\Round;
use Sports\Structure\NameService as StructureNameService;

class RoundCardDrawer
{
    protected Helper $helper;
    protected RoundDrawer $roundDrawer;


    public function __construct(
        protected StructureNameService $structureNameService,
        protected RoundConfig $config
    ) {
        $this->helper = new Helper();
        $this->roundDrawer = new RoundDrawer($structureNameService, $config);
    }

    // protected int $maxPoulesPerLine = 3;

//    public function drawRoundCard(
//        PdfPage $pfdPage,
//        Round $round,
//        Rectangle $rectangle,
//        StructureConfig $config): void
//    {
//        $cardHeaderRectangle = new Rectangle(
//            $rectangle->getTop(),
//            $rectangle->getTop()->addY(-$config->getRowHeight())
//        );
//        $this->drawRoundCardHeader($round, $cardHeaderRectangle, $config);
//        $cardBodyRectangle = new Rectangle($cardHeaderRectangle->getBottom(), $rectangle->getBottom());
//
//        //
//        $this->drawRound($round, $cardBodyRectangle, $config);
//    }
//
//    public function drawRoundCardHeader(Round $round, Rectangle $rectangle, StructureConfig $config): void
//    {
//        $roundName = $this->parent->getStructureService()->getRoundName($round);
//        $this->drawCell($roundName, $rectangle, Align::Center);
//    }


//        $nrOfPoules = count($poules);
//        $yPouleStart = $y;
//
//        $nrOfPouleRows = $this->getNrOfPouleRows($nrOfPoules);
//        for ($pouleRowNr = 1; $pouleRowNr <= $nrOfPouleRows; $pouleRowNr++) {
//            $nrOfPoulesForRow = $this->getNrOfPoulesForRow($nrOfPoules, $pouleRowNr === $nrOfPouleRows);
//            $pouleRowHeight = $this->getPouleRowHeight($poules, $nrOfPoulesForRow, $config->getRowHeight());
//            $yPouleEnd = $yPouleStart - $pouleRowHeight;
//            if ($yPouleStart !== $y && $yPouleEnd < self::PAGEMARGIN) {
//                break;
//            }
//            $yPouleStart = $this->drawPouleRow($poules, $nrOfPoulesForRow, $yPouleStart);
//            $yPouleStart -= $config->getRowHeight();
//        }

    public function getMinimalWidth( Round $round ): float
    {
        $minimalWidthHeader = $this->getHeaderMinimalWidth($round);
        $minimalWidthPoules = $this->roundDrawer->getMinimalWidth($round);
        return max($minimalWidthHeader, $minimalWidthPoules);
    }

    public function getHeaderMinimalWidth( Round $round ): float
    {
        $name = $this->structureNameService->getRoundName($round);
        $width = $this->helper->getTextWidth(
            ' ' . $name . ' ',
            $this->helper->getTimesFont(),
            $this->config->getFontHeight()
        );

        return $width;
    }
}
