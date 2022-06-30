<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\Structure;

use App\Export\Pdf\Configs\Structure\RoundConfig;
use App\Export\Pdf\Drawers\Helper;
use App\Export\Pdf\Page;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use Sports\Category;
use Sports\Structure\NameService as StructureNameService;

final class CategoryDrawer
{
    // private Helper $helper;
    private RoundCardDrawer $roundCardDrawer;

    // protected int $maxPoulesPerLine = 3;

    public function __construct(
        protected StructureNameService $structureNameService,
        protected bool $drawCategoryHeader
    ) {
        $this->helper = new Helper();
        $this->roundCardDrawer = new RoundCardDrawer($structureNameService, new RoundConfig());
    }

    public function drawCategory(Page $page, Category $category, Point $horLine, int $maxNrOfPouleRows): Point
    {
        if ($this->drawCategoryHeader) {
            $horLine = $this->drawHeader($category, $horLine);
        }
        $this->roundCardDrawer->drawRoundCard($page, $category->getRootRound(), $horLine);
    }

//
//    public function getRectangle(
//        Category $category,
//        RoundNumber $firstRoundNumer,
//        StructureConfig $config): Rectangle
//    {
//        // bekijk de width per structurecell als dit nog b
//
//        $minimalWidth = 0;
//        foreach( $roundNumberWi)
//        $minimalRoundWidth = $this->roundDrawer->getMinimalWidth($round);
//
//        return new Rectangle(new Point(0,0),new Point(1,1));
//    }

    public function calculateRectangle(Category $category, int $maxNrOfPouleRows, float $maxWidth): Rectangle
    {
        $minimalWidth = 0;

        $height = 0;
        if ($this->drawCategoryHeader) {
            $height += $this->getHeaderHeight() + PADDING;
        }

        // bepaal de maximale width vanaf ronde 2
        // als geen ronde 2 dan return $maxWidth

        // bepaal daarna de hoogte van ronde 1 en tel deze bij de rectangle op


        $firstRoundMinimalWidth = $pouleDrawer->getMinimalWidth + 2xpadding;

        $withCompetitors = $this->drawWithCompetitors($structureCell);
        foreach ($structureCell->getRounds() as $round) {
            if ($withCompetitors) {
                continue;
            }
            $minimalWidth += $this->roundCardDrawer->calculateRectangle($round, $maxNrOfPouleRows);
        }
        return $minimalWidth;
    }


//    public function getHeight(): float {
//
//    }

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
//        foreach( $poules as $poule ) {
//            $pouleRectangle = $this->getPouleRectangle($startPoint, $poule, $withCompetitors);
//            $this->drawPoule($poule, $pouleRectangle, $withCompetitors);
//            $startPoint = $pouleRectangle->getTopRight()->addX($config->getPouleMargin());
//            if( $startPoint->getX() == $rectangle->getRight()->getX() ) {
//
//            }
//        }
////        $nrOfPoules = count($poules);
////        $yPouleStart = $y;
////
////        $nrOfPouleRows = $this->getNrOfPouleRows($nrOfPoules);
////        for ($pouleRowNr = 1; $pouleRowNr <= $nrOfPouleRows; $pouleRowNr++) {
////            // $nrOfPoulesForRow = $this->getNrOfPoulesForRow($nrOfPoules, $pouleRowNr === $nrOfPouleRows);
////            $pouleRowHeight = $this->getPouleRowHeight($poules, $nrOfPoulesForRow);
////            $yPouleEnd = $yPouleStart - $pouleRowHeight;
////            if ($yPouleStart !== $y && $yPouleEnd < self::PAGEMARGIN) {
////                break;
////            }
////            $yPouleStart = $this->drawPouleRow($poules, $nrOfPoulesForRow, $yPouleStart);
////            $yPouleStart -= $config->getRowHeight();
////        }
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
