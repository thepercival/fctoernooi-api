<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\Structure;

use App\Export\Pdf\Align;
use App\Export\Pdf\Configs\Structure\RoundConfig;
use App\Export\Pdf\Drawers\Helper;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page;
use App\Export\Pdf\Rectangle;
use Sports\Poule;
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

    public function drawRoundCard(Page $page, Round $round, HorizontalLine $top): HorizontalLine
    {
        $headerBottom = $this->drawRoundCardHeader($page, $round, $top);
        // $cardBodyRectangle = new Rectangle($cardHeaderRectangle->getBottom(), $rectangle->getBottom());

        $poulesTop = $headerBottom->addY($this->config->getPadding());
        $poulesBottom = $this->drawPoules($page, $round, $poulesTop);
        return $poulesBottom->addY($this->config->getPadding());
    }

    public function drawRoundCardHeader(Page $page, Round $round, HorizontalLine $horLine): HorizontalLine
    {
        $rectangle = new Rectangle($horLine, -$this->config->getHeaderHeight());
        $roundName = $page->getParent()->getStructureService()->getRoundName($round);
        $page->drawCell($roundName, $rectangle, Align::Center);
        return $horLine->addY(-$this->config->getHeaderHeight());
    }

    protected function drawPoules(Page $page, Round $round, HorizontalLine $startHorLine): HorizontalLine
    {
        $showPouleNamePrefix = $round->isRoot();
        $showCompetitor = $round->isRoot();

        $startPoulesHorLine = $startHorLine;
        $currentHorLine = $startPoulesHorLine;
        $drawnPoules = [];

        $pouleDrawer = new PouleDrawer($this->structureNameService, $this->config->getPouleConfig());
        $poules = $round->getPoules()->toArray();
        while ($poule = array_shift($poules)) {
            $minimalPouleWidth = $pouleDrawer->getMinimalWidth($poule, $showPouleNamePrefix, $showCompetitor);
            if ($minimalPouleWidth > $currentHorLine->getWidth()) {
                $startPoulesHorLine = $startPoulesHorLine->addY(-$this->getMaximumHeight($drawnPoules));
                $drawnPoules = [];
                if (!empty($poules)) {
                    $startPoulesHorLine = $startPoulesHorLine->addY(-$this->config->getPadding());
                }
                $currentHorLine = $startPoulesHorLine;
            }
            $drawnPoules[] = $poule;
        }
        return $startPoulesHorLine->addY(-$this->getMaximumHeight($drawnPoules));
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
    }


    public function getMinimalWidth(Round $round, int $maxNrOfPouleRows): float
    {
        $minimalWidthHeader = $this->getHeaderMinimalWidth($round);
        $minimalWidthPoules = $this->roundDrawer->getMinimalWidth($round, $maxNrOfPouleRows);
        return max($minimalWidthHeader, $minimalWidthPoules);
    }

    public function getHeaderMinimalWidth(Round $round): float
    {
        $name = $this->structureNameService->getRoundName($round);
        $width = $this->helper->getTextWidth(
            ' ' . $name . ' ',
            $this->helper->getTimesFont(),
            $this->config->getFontHeight()
        );

        return $width;
    }

    /**
     * @param list<Poule> $poules
     * @return float
     */
    public function getMaximumHeight(array $poules): float
    {
        $maxHeight = 0;
        $pouleDrawer = new PouleDrawer($this->structureNameService, $this->config->getPouleConfig());
        foreach ($poules as $poule) {
            $height = $pouleDrawer->getHeight($poule);
            if ($height > $maxHeight) {
                $maxHeight = $height;
            }
        }
        return $maxHeight;
    }
}
