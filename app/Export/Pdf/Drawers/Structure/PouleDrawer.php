<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\Structure;

use App\Export\Pdf\Align;
use App\Export\Pdf\Configs\Structure\PouleConfig;
use App\Export\Pdf\Drawers\Helper;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use Sports\Place;
use Sports\Poule;
use Sports\Structure\NameService as StructureNameService;

final class PouleDrawer
{
    public const MAX_NR_PLACES_IN_COLUMN = 10;

    protected Helper $helper;
    private float|null $numberColumnWidth = null;

    public function __construct(
        protected StructureNameService $structureNameService,
        protected PouleConfig $config
    ) {
        $this->helper = new Helper();
    }

    public function renderPoule(
        Poule $poule,
        bool $showPouleNamePrefix,
        bool $showCompetitor,
        HorizontalLine $top,
        Page $page
    ): void {
        $rowHeight = $this->config->getRowHeight();

        $pouleName = $this->structureNameService->getPouleName($poule, $showPouleNamePrefix);
        $pouleNameRectangle = new Rectangle($top, -$rowHeight);
        $page->setFont($this->helper->getTimesFont(true), $this->config->getFontHeight());
        $page->drawCell($pouleName, $pouleNameRectangle, Align::Center, 'black');
        $placesTop = $pouleNameRectangle->getBottom();

        $page->setFont($this->helper->getTimesFont(), $this->config->getFontHeight());

        $left = $placesTop->getStart()->getX();
        $places = array_values($poule->getPlaces()->toArray());
        $nrOfPlacesInColumn = $this->getNrOfPlacesInColumn($poule);
        $placesToRender = array_splice($places, 0, $nrOfPlacesInColumn);
        while (count($placesToRender) > 0) {
            $columnWidth = $this->calculatePlacesWidth($placesToRender, $showCompetitor);

            $placeTop = new HorizontalLine(new Point($left, $placesTop->getY()), $columnWidth);
            foreach ($placesToRender as $placeToRender) {
                $placeTop = $this->renderPlace($placeToRender, $showCompetitor, $placeTop, $page);
            }

            $placesToRender = array_splice($places, 0, $nrOfPlacesInColumn);
            $left += $columnWidth;
        }
    }

    public function renderPlace(Place $place, bool $showCompetitor, HorizontalLine $top, Page $page): HorizontalLine
    {
        $rowHeight = $this->config->getRowHeight();
        $numberWidth = $this->getNumberColumnWidth();

        $numberTop = new HorizontalLine($top->getStart(), $numberWidth);
        $numberRectangle = new Rectangle($numberTop, -$rowHeight);
        $page->drawCell((string)$place->getPlaceNr(), $numberRectangle, Align::Right, 'black');
        if ($showCompetitor) {
            $name = '';
            $startLocation = $place->getStartLocation();
            $startLocationMap = $this->structureNameService->getStartLocationMap();
            if ($startLocationMap !== null && $startLocation !== null && $startLocationMap->getCompetitor(
                    $startLocation
                ) !== null) {
                $name = $this->structureNameService->getPlaceName($place, true);
            }
            $nameTop = new HorizontalLine($numberTop->getEnd(), $top->getWidth() - $numberTop->getWidth());
            $page->drawCell($name, new Rectangle($nameTop, -$rowHeight), Align::Left, 'black');
        }
        return $top->addY(-$rowHeight);
    }

    public function calculateWidth(Poule $poule, bool $showPouleNamePrefix, bool $showCompetitor): float
    {
        $pouleNameWidth = $this->calculatePouleNameWidth($poule, $showPouleNamePrefix);
        $placesWidth = $this->calculateAllPlacesWidth($poule, $showCompetitor);
        return max($pouleNameWidth, $placesWidth);
    }

    private function calculatePouleNameWidth(Poule $poule, bool $showPouleNamePrefix): float
    {
        $pouleName = $this->structureNameService->getPouleName($poule, $showPouleNamePrefix);
        return $this->getTextWidth($pouleName);
    }

    // could be extended with maxNrOfRows, maxNrOfColumns
    public function calculateAllPlacesWidth(Poule $poule, bool $showCompetitor): float
    {
        $minimalWidth = 0;
        $places = array_values($poule->getPlaces()->toArray());
        $nrOfPlacesInColumn = $this->getNrOfPlacesInColumn($poule);
        $placesToCalculate = array_splice($places, 0, $nrOfPlacesInColumn);
        while (count($placesToCalculate) > 0) {
            $minimalWidth += $this->calculatePlacesWidth($placesToCalculate, $showCompetitor);
            $placesToCalculate = array_splice($places, 0, $nrOfPlacesInColumn);
        }
        return $minimalWidth;
    }

    /**
     * @param list<Place> $places
     * @param bool $showCompetitor
     * @return float
     */
    public function calculatePlacesWidth(array $places, bool $showCompetitor): float
    {
        $minimalWidth = 0;
        foreach ($places as $place) {
            $placeWidth = $this->calculatePlaceWidth($place, $showCompetitor);
            if ($placeWidth > $minimalWidth) {
                $minimalWidth = $placeWidth;
            }
        }
        return $minimalWidth;
    }

    // could be extended with maxNrOfRows, maxNrOfColumns
    public function calculatePlaceWidth(Place $place, bool $showCompetitor): float
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


    public function getHeight(Poule $poule): float
    {
        $nrOfColumns = $this->getNrOfColumnsNeeded($poule);
        $nrOfPlaces = count($poule->getPlaces());
        $nrOfRows = ceil($nrOfPlaces / $nrOfColumns);
        return $this->config->getRowHeight() * (1 + $nrOfRows);
    }

    public function getNrOfColumnsNeeded(Poule $poule): float
    {
        $rest = count($poule->getPlaces()) % self::MAX_NR_PLACES_IN_COLUMN;
        $nrOfColumns = (count($poule->getPlaces()) - $rest) / self::MAX_NR_PLACES_IN_COLUMN;
        return $rest === 0 ? $nrOfColumns : $nrOfColumns + 1;
    }

    public function getNrOfPlacesInColumn(Poule $poule): int
    {
        $nrOfColumns = $this->getNrOfColumnsNeeded($poule);
        return (int)ceil(count($poule->getPlaces()) / $nrOfColumns);
    }

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
