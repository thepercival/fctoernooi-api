<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\Structure;

use App\Export\Pdf\Configs\Structure\PouleConfig;
use App\Export\Pdf\Drawers\Helper;
use Sports\Place;
use Sports\Poule;
use Sports\Structure\NameService as StructureNameService;

final class PouleDrawer
{
    protected Helper $helper;
    private float|null $numberColumnWidth = null;

    public function __construct(
        protected StructureNameService $structureNameService,
        protected PouleConfig $config
    ) {
        $this->helper = new Helper();
    }

    public function getMinimalWidth(Poule $poule, bool $showPouleNamePrefix, bool $showCompetitor): float
    {
        $minimalPouleNameWidth = $this->getMinimalPouleNameWidth($poule, $showPouleNamePrefix);
        $minimalPlacesWidth = $this->getMinimalPlacesWidth($poule, $showCompetitor);
        return max($minimalPouleNameWidth, $minimalPlacesWidth);
    }

    public function getMinimalPouleNameWidth(Poule $poule, bool $showPouleNamePrefix): float
    {
        $pouleName = $this->structureNameService->getPouleName($poule, $showPouleNamePrefix);
        return $this->getTextWidth($pouleName);
    }

    // could be extended with maxNrOfRows, maxNrOfColumns
    public function getMinimalPlacesWidth(Poule $poule, bool $showCompetitor): float
    {
        $minimalWidth = 0;
        foreach ($poule->getPlaces() as $place) {
            $placeWidth = $this->getMinimalPlaceWidth($place, $showCompetitor);
            if ($placeWidth > $minimalWidth) {
                $minimalWidth = $placeWidth;
            }
        }
        return $minimalWidth;
    }

    // could be extended with maxNrOfRows, maxNrOfColumns
    public function getMinimalPlaceWidth(Place $place, bool $showCompetitor): float
    {
        $minimalWidth = 0;
        $startLocationMap = $this->structureNameService->getStartLocationMap();
        if ($showCompetitor) {
            $minimalWidth += $this->getNumberColumnWidth();
            if ($startLocationMap !== null) {
                $startLocation = $place->getStartLocation();
                if ($startLocation !== null) {
                    $competitor = $startLocationMap->getCompetitor($startLocation);
                    if ($competitor !== null) {
                        $minimalWidth += $this->getTextWidth($competitor->getName());
                    }
                }
            }
        } else {
            $placeFromName = $this->structureNameService->getPlaceFromName($place, false);
            $minimalWidth += $this->getTextWidth($placeFromName);
        }
        return $minimalWidth;
    }

    private function getNumberColumnWidth(): float
    {
        if ($this->numberColumnWidth === null) {
            $this->numberColumnWidth = $this->getTextWidth('88');
        }
        return $this->numberColumnWidth;
    }

    private function getTextWidth(string $text, bool $withPadding = true): float
    {
        $textWidth = $this->helper->getTextWidth(
            $text,
            $this->helper->getTimesFont(),
            $this->config->getFontHeight()
        );
        if ($withPadding === true) {
            return $this->config->getPaddingX() + $textWidth + $this->config->getPaddingX();
        }
        return $textWidth;
    }

//    // protected int $maxPoulesPerLine = 3;
//
//    public function drawRound(Round $round, Rectangle $rectangle, StructureConfig $config ): void
//    {
//        $withCompetitors = $round->isRoot();
//        // $y = $this->drawHeader("indeling");
//        $this->drawGrouping($round->getPoules()->toArray(), $rectangle, $config, $withCompetitors);
//    }
//
//    public function getHeight(): float {
//
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
//
//        foreach( $poules as $poule ) {
//
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
