<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\Structure;

use App\Export\Pdf\Configs\Structure\RoundConfig;
use App\Export\Pdf\Drawers\Helper;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page;
use App\Export\Pdf\Poule\PouleWidth;
use Sports\Poule;
use Sports\Round;
use Sports\Structure\NameService as StructureNameService;

class RoundDrawer
{
    protected PouleDrawer $pouleDrawer;
    protected Helper $helper;

    public function __construct(
        protected StructureNameService $structureNameService,
        protected RoundConfig $config
    ) {
        $this->helper = new Helper();
        $this->pouleDrawer = new PouleDrawer($structureNameService, $config->getPouleConfig());
    }

    public function renderPoules(Round $round, HorizontalLine $startHorLine, Page|null $page): float
    {
        $showPouleNamePrefix = $round->isRoot();
        $showCompetitor = $round->isRoot();

        $poulesRowTop = $startHorLine;
        $pouleDrawer = new PouleDrawer($this->structureNameService, $this->config->getPouleConfig());
        $poules = array_values($round->getPoules()->toArray());
        while ($rowPoules = $this->getRowPoules($round, $poules, $poulesRowTop->getWidth())) {
            list($marginLeft, $marginX) = $this->getPouleMarginX($round, $rowPoules, $poulesRowTop->getWidth());
            $pouleTopLeft = $poulesRowTop->getStart()->addX($marginLeft);
            foreach ($rowPoules as $poule) {
                $pouleWidth = $pouleDrawer->calculateWidth($poule, $showPouleNamePrefix, $showCompetitor);
                $pouleTop = new HorizontalLine($pouleTopLeft, $pouleWidth);
                if ($page) {
                    $this->pouleDrawer->renderPoule($poule, $showPouleNamePrefix, $showCompetitor, $pouleTop, $page);
                }
                $pouleTopLeft = $pouleTopLeft->addX($pouleWidth)->addX($marginX);
            }

            $maxHeight = $this->calculateMaximumHeight($rowPoules);
            $poulesRowTop = $poulesRowTop->addY(-$maxHeight);
            if (!empty($poules)) {
                $poulesRowTop = $poulesRowTop->addY(-$this->config->getPouleConfig()->getMargin());
            }
        }

//        while ($poule = array_shift($poules)) {
//            $pouleWidth = $pouleDrawer->calculateWidth($poule, $showPouleNamePrefix, $showCompetitor);
//            if (($pouleTopLeft->getX() + $pouleWidth) > $poulesRowTop->getEnd()->getX()) {
//                $poulesRowTop = $poulesRowTop->addY(-$this->calculateMaximumHeight($drawnPoules));
//                if (!empty($poules)) {
//                    $poulesRowTop = $poulesRowTop->addY(-$this->config->getPadding());
//                }
//                $pouleTopLeft = $poulesRowTop->getStart();
//                $drawnPoules = [];
//            }
//            $pouleTop = new HorizontalLine($pouleTopLeft, $pouleWidth);
//            if ($page) {
//                $this->pouleDrawer->renderPoule($poule, $showPouleNamePrefix, $showCompetitor, $pouleTop, $page);
//            }
//            $pouleTopLeft = $pouleTop->getEnd()->addX($this->config->getPadding());
//            $drawnPoules[] = $poule;
//        }
        return $poulesRowTop->getY();
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


    /**
     * @param Round $round ,
     * @param list<Poule> $rowPoules
     * @param float $totalWidth
     * @return list<float>
     */
    private function getPouleMarginX(Round $round, array $rowPoules, float $totalWidth): array
    {
        $showPouleNamePrefix = $round->isRoot();
        $showCompetitor = $round->isRoot();
        $pouleDrawer = new PouleDrawer($this->structureNameService, $this->config->getPouleConfig());

        $pouleWidths = array_map(
            function (Poule $poule) use ($showPouleNamePrefix, $showCompetitor, $pouleDrawer): PouleWidth {
                return new PouleWidth(
                    $pouleDrawer->calculateWidth($poule, $showPouleNamePrefix, $showCompetitor),
                    $poule
                );
            },
            $rowPoules
        );

        $poulesWidth = array_sum(array_map(fn(PouleWidth $pouleWidth) => $pouleWidth->getWidth(), $pouleWidths));

        $totalMarginWidth = $totalWidth - $poulesWidth;
        $aroundNrOfMargins = count($rowPoules) + 1;
        $aroundMargin = $totalMarginWidth / $aroundNrOfMargins;
        if ($aroundMargin < $this->config->getPouleConfig()->getMargin() && count($rowPoules) > 1) {
            $betweenNrOfMargins = count($rowPoules) - 1;
            $betweenMargin = $totalMarginWidth / $betweenNrOfMargins;
            return [0, $betweenMargin];
        }

//        if ($round->isRoot()) {
//            if (count($rowPoules) > 1) {
//                $nrOfPaddings = count($rowPoules) + 1;
//                return [0, ($totalWidth - $poulesWidth) / $nrOfPaddings];
//            }
//        }
        return [$aroundMargin, $aroundMargin];
    }

    /**
     * @param Round $round ,
     * @param list<Poule> $poules
     * @param float $width
     * @return list<Poule>
     */
    private function getRowPoules(Round $round, array &$poules, float $width): array
    {
        $showPouleNamePrefix = $round->isRoot();
        $showCompetitor = $round->isRoot();
        $pouleDrawer = new PouleDrawer($this->structureNameService, $this->config->getPouleConfig());
        $poulesWidth = 0;
        $poulesForRow = [];
        while ($poule = array_shift($poules)) {
            $pouleWidth = $pouleDrawer->calculateWidth($poule, $showPouleNamePrefix, $showCompetitor);
            if (($poulesWidth + $pouleWidth) > $width) {
                array_unshift($poules, $poule);
                return $poulesForRow;
            }
            $poulesWidth += $pouleWidth + $this->config->getPouleConfig()->getMargin();
            $poulesForRow[] = $poule;
        }
        return $poulesForRow;
    }

    public function calculateMinimalWidth(Round $round, int $maxNrOfPouleRows): float
    {
        $poules = $round->getPoules()->toArray();
        $nrOfPoulesBiggestRow = (int)ceil(count($poules) / $maxNrOfPouleRows);
        $poulesBiggestRow = array_splice($poules, 0, $nrOfPoulesBiggestRow);

        $pouleMargin = $this->config->getPouleConfig()->getMargin();
        $pouleDrawer = new PouleDrawer($this->structureNameService, $this->config->getPouleConfig());

        $minimalWidth = 0;
        if ($round->isRoot()) {
            foreach ($poulesBiggestRow as $poule) {
                $minPouleWidth = $pouleDrawer->calculateWidth($poule, true, true);
                if ($minimalWidth === 0 || $minPouleWidth > $minimalWidth) {
                    $minimalWidth = $minPouleWidth;
                }
            }
        } else {
            foreach ($poulesBiggestRow as $poule) {
                $minimalWidth += $pouleDrawer->calculateWidth($poule, false, false);
                $minimalWidth += $pouleMargin;
            }
        }
        return $pouleMargin + $minimalWidth + $pouleMargin;
    }

    /**
     * @param list<Poule> $poules
     * @return float
     */
    private function calculateMaximumHeight(array $poules): float
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

//    public function drawRound(Round $round, Rectangle $rectangle, StructureConfig $config): void
//    {
//        $withCompetitors = $round->isRoot();
//        // $y = $this->drawHeader("indeling");
//        $this->drawGrouping($round->getPoules()->toArray(), $rectangle, $config, $withCompetitors);
//    }
//
//    public function getHeight(): float
//    {
//    }
//
//    /**
//     * @param list<Poule> $poules
//     * @param Rectangle $rectangle
//     * @param StructureConfig $config
//     * @param bool $withCompetitors
//     */
//    public function drawPoules(array $poules, Rectangle $rectangle, StructureConfig $config, bool $withCompetitors): void
//    {
//        // $y = $this->drawSubHeader("Indeling", $y);
//        $startPoint = $rectangle->getStart()->add($config->getPouleMargin(), $config->getPouleMargin());
//        foreach ($poules as $poule) {
//            $pouleRectangle = $this->getPouleRectangle($startPoint, $poule, $withCompetitors);
//            $this->drawPoule($poule, $pouleRectangle, $withCompetitors);
//            $startPoint = $pouleRectangle->getTopRight()->addX($config->getPouleMargin());
//            if ($startPoint->getX() == $rectangle->getRight()->getX()) {
//            }
//        }
//        $nrOfPoules = count($poules);
//        $yPouleStart = $y;
//
//        $nrOfPouleRows = $this->getNrOfPouleRows($nrOfPoules);
//        for ($pouleRowNr = 1; $pouleRowNr <= $nrOfPouleRows; $pouleRowNr++) {
//            // $nrOfPoulesForRow = $this->getNrOfPoulesForRow($nrOfPoules, $pouleRowNr === $nrOfPouleRows);
//            $pouleRowHeight = $this->getPouleRowHeight($poules, $nrOfPoulesForRow);
//            $yPouleEnd = $yPouleStart - $pouleRowHeight;
//            if ($yPouleStart !== $y && $yPouleEnd < self::PAGEMARGIN) {
//                break;
//            }
//            $yPouleStart = $this->drawPouleRow($poules, $nrOfPoulesForRow, $yPouleStart);
//            $yPouleStart -= $config->getRowHeight();
//        }
//    }
//
//    /**
//     * @param list<Poule> $poules
//     * @param int $nrOfPoulesForRow
//     * @param float $y
//     * @return float
//     */
//    protected function drawPouleRow(array &$poules, int $nrOfPoulesForRow, float $y, int $rowHeight): float
//    {
//        $pouleMargin = 20;
//        $pouleWidth = $this->getPouleWidth($nrOfPoulesForRow, $pouleMargin);
//        $x = $this->getXLineCentered($nrOfPoulesForRow, $pouleWidth, $pouleMargin);
//        $lowestY = 0;
//
//        while ($nrOfPoulesForRow > 0) {
//            $poule = array_shift($poules);
//            if ($poule === null) {
//                break;
//            }
//            $yEnd = $this->drawPoule($poule, $x, $pouleWidth, $y, $rowHeight);
//            if ($lowestY === 0 || $yEnd < $lowestY) {
//                $lowestY = $yEnd;
//            }
//            $x += $pouleMargin + $pouleWidth;
//
//            $nrOfPoulesForRow--;
//        }
//        return $lowestY;
//    }
//
//    protected function drawPoule(Poule $poule, float $x, float $pouleWidth, float $yStart, int $rowHeight): float
//    {
//        $fontHeight = $rowHeight - 4;
//
//        $numberWidth = $pouleWidth * 0.1;
//        $this->setFont($this->helper->getTimesFont(true), $fontHeight);
//        $this->drawCell(
//            $this->getStructureNameService()->getPouleName($poule, true),
//            $x,
//            $yStart,
//            $pouleWidth,
//            $rowHeight,
//            Align::Center,
//            "black"
//        );
//        $this->setFont($this->helper->getTimesFont(), $fontHeight);
//        $y = $yStart - $rowHeight;
//        foreach ($poule->getPlaces() as $place) {
//            $this->drawCell(
//                (string)$place->getPlaceNr(),
//                $x,
//                $y,
//                $numberWidth,
//                $rowHeight,
//                Align::Right,
//                "black"
//            );
//            $name = '';
//            $startLocation = $place->getStartLocation();
//            if ($startLocation !== null && $this->parent->getStartLocationMap()->getCompetitor($startLocation) !== null) {
//                $name = $this->getStructureNameService()->getPlaceName($place, true);
//            }
//            $this->drawCell(
//                $name,
//                $x + $numberWidth,
//                $y,
//                $pouleWidth - $numberWidth,
//                $rowHeight,
//                Align::Left,
//                "black"
//            );
//            $y -= $rowHeight;
//        }
//        return $y;
//    }
//
//    protected function getNrOfPouleRows(int $nrOfPoules): int
//    {
//        if (($nrOfPoules % 3) !== 0) {
//            $nrOfPoules += (3 - ($nrOfPoules % 3));
//        }
//        return (int)($nrOfPoules / 3);
//    }
//
//    /**
//     * @param list<Poule> $poules
//     * @param int $nrOfPoulesForRow
//     * @param int $rowHeight
//     * @return float
//     */
//    protected function getPouleRowHeight(array $poules, int $nrOfPoulesForRow, int $rowHeight): float
//    {
//        $maxPouleHeight = 0;
//        for ($pouleNr = 1; $pouleNr <= $nrOfPoulesForRow; $pouleNr++) {
//            $poule = array_shift($poules);
//            if ($poule === null) {
//                continue;
//            }
//            $pouleHeight = $this->getPouleHeight($poule, $rowHeight);
//            if ($pouleHeight > $maxPouleHeight) {
//                $maxPouleHeight = $pouleHeight;
//            }
//        }
//        return $maxPouleHeight;
//    }
//
//    protected function getPouleHeight(Poule $poule, int $rowHeight): float
//    {
//        return $rowHeight + (count($poule->getPlaces()) * $rowHeight);
//    }
//
//    protected function getNrOfPoulesForRow(int $nrOfPoules, bool $lastLine): int
//    {
//        if ($nrOfPoules === 4) {
//            return 2;
//        }
//        if ($nrOfPoules <= 3) {
//            return $nrOfPoules;
//        }
//        if (!$lastLine) {
//            return 3;
//        }
//        if (($nrOfPoules % 3) === 0) {
//            return 3;
//        }
//        return ($nrOfPoules % 3);
//    }
//
//    protected function getPouleWidth(int $nrOfPoules, float $margin): float
//    {
//        if ($nrOfPoules === 1) {
//            $nrOfPoules++;
//        }
//        return ($this->getDisplayWidth() - (($nrOfPoules - 1) * $margin)) / $nrOfPoules;
//    }

//    /**
//     * maximaal 4 poules in de breedte
//     */
//    protected function getXLineCentered(int $nrOfPoules, float $pouleWidth, float $margin): float
//    {
//        if ($nrOfPoules > $this->maxPoulesPerLine) {
//            $nrOfPoules = $this->maxPoulesPerLine;
//        }
//        $width = ($nrOfPoules * $pouleWidth) + (($nrOfPoules - 1) * $margin);
//        return self::PAGEMARGIN + ($this->getDisplayWidth() - $width) / 2;
//    }
}
