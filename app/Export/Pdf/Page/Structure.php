<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Document\Structure as StructureDocument;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Point;

/**
 * @template-extends ToernooiPdfPage<StructureDocument>
 */
class Structure extends ToernooiPdfPage
{
//    use RoundCardDrawer;
//    use RoundDrawer;
//    use CategoryDrawer;

    // public const RowHeight = 18;
    // public const FontHeight = self::RowHeight - 4;
    private const ROUNDMARGIN = 10;

    // private bool $enableOutOfBoundsException;
    private int $maxNrOfPoulePlaceColumns = 1;

    public function __construct(StructureDocument $document, Point $point)
    {
        $dimensions = $point->getX() . ':' . $point->getY();
        parent::__construct($document, $dimensions);
        // $this->setFont($this->helper->getTimesFont(), $document->getConfig()->getFontHeight());
        $this->setLineWidth(0.5);
//        $this->enableOutOfBoundsException = $enableOutOfBoundsException;
    }

//    public function getParent(): StructureDocument
//    {
//        return $this->parent;
//    }

//    public function setMaxNrOfPoulePlaceColumns(int $maxNrOfPoulePlaceColumns): void
//    {
//        $this->maxNrOfPoulePlaceColumns = $maxNrOfPoulePlaceColumns;
//    }

//    //TODO CDK
//    public function drawCategory2(Category $category, RoundNumber $firstRoundNumber ): self
//    {
//        $config = new StructureConfig(
//            10,
//            30,
//            18,
//            10,
//        );
//        // check if height can be on page , else clone this
//        // Category $category, Line $top, StructureConfig $config
//
//        ALS ROUND NOG OP PAGINA DAN AFDRUKKEN, ANDERS NIEUWE PAGINA
//        $this->drawRoundCard( $category->getRootRound(), $rectangle, $config );
//        $this->drawGrouping();
//
//        // kijk per roundnumber de height en kijk zo als de volgende roundnumber op een nieuwe pagina moet

//
//        $newPage = $this->parent->createStructurePage(new Point($this->getWidth(), $this->getWidth()));
//
//        // meteen verder gaan op nieuwe pagina
//
//        foreach ($this->parent->getStructure()->getCategories() as $category) {
//            $rooRound = $category->getRootRound();
//            $y = $this->drawHeader('opzet & indeling');
//            $y = $this->drawSubHeader('Opzet  & indeling', $y);
//            $this->drawRound($rooRound, $y, self::PAGEMARGIN, $this->getDisplayWidth());
//        }
//    }


//    protected function drawRound(Round $round, float $y, float $x, float $width): void
//    {
////        if ($this->enableOutOfBoundsException && $width < ((self::PlaceWidth * 2) + self::PouleMargin)) {
////            throw new PdfOutOfBoundsException('X', E_ERROR);
////        }
//
//        $this->setFont($this->helper->getTimesFont(true), self::FontHeight);
//
//        $arrLineColors = !$round->isRoot() ? ['t' => 'black'] : null;
//        $roundName = $this->getStructureNameService()->getRoundName($round);
//        if( !$this->parent->getStructure()->hasSingleCategory() ) {
//            $roundName .= ' - ' . $round->getCategory()->getName();
//        }
//        $this->drawCell($roundName, $x, $y, $width, self::RowHeight, Align::Center, $arrLineColors);
//        $y -= self::RowHeight;
//
//        if ($round->getPoules()->count() === 1 && $round->getPoule(1)->getPlaces()->count() < 3) {
//            return;
//        }
//        $this->setFont($this->helper->getTimesFont(), self::FontHeight);
//
//        $poules = array_values($round->getPoules()->toArray());
//        if( $round->isRoot() ) {
//            $y = $this->drawStartRoundPoules($poules, $x, $y, $width);
//        } else {
//            $y = $this->drawPoules($poules, $x, $y, $width);
//        }
//
//        $nrOfChildren = count($round->getChildren());
//        if ($nrOfChildren === 0) {
//            return;
//        }
//
//        $widthRoundMargins = (count($round->getChildren()) - 1) * self::RoundMargin;
//        $width -= $widthRoundMargins;
//        foreach ($round->getChildren() as $childRound) {
//            $widthChild = $childRound->getNrOfPlaces() / $round->getNrOfPlacesChildren() * $width;
//            $this->drawRound($childRound, $y, $x, $widthChild);
//            $x += $widthChild + self::RoundMargin;
//        }
//    }

//    protected function drawRound(Round $round, float $y, float $x, float $width): void
//    {
////        if ($this->enableOutOfBoundsException && $width < ((self::PlaceWidth * 2) + self::PouleMargin)) {
////            throw new PdfOutOfBoundsException('X', E_ERROR);
////        }
//
//        $this->setFont($this->helper->getTimesFont(true), self::FontHeight);
//
//        $arrLineColors = !$round->isRoot() ? ['t' => 'black'] : null;
//        $roundName = $this->getStructureNameService()->getRoundName($round);
//        if( !$this->parent->getStructure()->hasSingleCategory() ) {
//            $roundName .= ' - ' . $round->getCategory()->getName();
//        }
//        $this->drawCell($roundName, $x, $y, $width, self::RowHeight, Align::Center, $arrLineColors);
//        $y -= self::RowHeight;
//
//        if ($round->getPoules()->count() === 1 && $round->getPoule(1)->getPlaces()->count() < 3) {
//            return;
//        }
//        $this->setFont($this->helper->getTimesFont(), self::FontHeight);
//
//        $poules = array_values($round->getPoules()->toArray());
//        if( $round->isRoot() ) {
//            $y = $this->drawStartRoundPoules($poules, $x, $y, $width);
//        } else {
//            $y = $this->drawPoules($poules, $x, $y, $width);
//        }
//
//        $nrOfChildren = count($round->getChildren());
//        if ($nrOfChildren === 0) {
//            return;
//        }
//
//        $widthRoundMargins = (count($round->getChildren()) - 1) * self::RoundMargin;
//        $width -= $widthRoundMargins;
//        foreach ($round->getChildren() as $childRound) {
//            $widthChild = $childRound->getNrOfPlaces() / $round->getNrOfPlacesChildren() * $width;
//            $this->drawRound($childRound, $y, $x, $widthChild);
//            $x += $widthChild + self::RoundMargin;
//        }
//    }

//    protected function getMaxNrOfPlaceColumnsPerPoule(int $nrOfPoules, float $width): int
//    {
//        $pouleMarginsWidth = ($nrOfPoules - 1) * self::PouleMargin;
//        $pouleWidth = ($width - $pouleMarginsWidth) / $nrOfPoules;
//        $maxNrOfPlaceColumnsPerPoule = (int)floor($pouleWidth / self::PlaceWidth);
//        if ($maxNrOfPlaceColumnsPerPoule === 0) {
//            $maxNrOfPlaceColumnsPerPoule = 1;
//        }
//        return $maxNrOfPlaceColumnsPerPoule;
//    }
//
//    protected function getNrOfPlaceColumns(Poule $poule, int $maxNrOfPlaceColumnsPerPoule): int
//    {
//        $nrOfPlaceColumnsPerPoule = $poule->getPlaces()->count();
//        if ($nrOfPlaceColumnsPerPoule > $maxNrOfPlaceColumnsPerPoule) {
//            $nrOfPlaceColumnsPerPoule = $maxNrOfPlaceColumnsPerPoule;
//        }
//        return $nrOfPlaceColumnsPerPoule;
//    }

//    /**
//     * @param list<Poule> $poules
//     * @param float $x
//     * @param float $y
//     * @param float $width
//     * @return float
//     * @throws PdfOutOfBoundsException
//     */
//    protected function drawStartRoundPoules(array $poules, float $x, float $y, float $width): float
//    {
//        // $maxNrOfPlaceColumnsPerPoule = $this->getMaxNrOfPlaceColumnsPerPoule(count($poules), $width);
//        $maxNrOfPlaceColumnsPerPoule = $this->maxNrOfPoulePlaceColumns;
//        if ($this->enableOutOfBoundsException && $this->maxNrOfPoulePlaceColumns < 1) {
//            throw new PdfOutOfBoundsException('X', E_ERROR);
//        }
//
//        $xStart = $x;
//        while (count($poules) > 0) {
//            $poulesForLine = $this->reduceForLine($poules, $maxNrOfPlaceColumnsPerPoule, $width);
//            $x = $this->getXForCentered($poulesForLine, $width, $xStart, $maxNrOfPlaceColumnsPerPoule);
//            foreach ($poulesForLine as $poule) {
//                $nrOfPlaceColumnsPerPoule = $this->maxNrOfPoulePlaceColumns; // $this->getNrOfPlaceColumns($poule, $maxNrOfPlaceColumnsPerPoule);
//                $pouleWidth = $nrOfPlaceColumnsPerPoule * self::PlaceWidth;
//                $this->drawPoule($poule, $x, $y, $nrOfPlaceColumnsPerPoule);
//                $x += $pouleWidth + self::PouleMargin;
//            }
//            $y -= ($this->getMaxHeight($poulesForLine, $maxNrOfPlaceColumnsPerPoule) + self::PouleMargin);
//        }
//        return $y;
//    }

//    /**
//     * @param list<Poule> $poules
//     * @param float $x
//     * @param float $y
//     * @param float $width
//     * @return float
//     * @throws PdfOutOfBoundsException
//     */
//    protected function drawPoules(array $poules, float $x, float $y, float $width): float
//    {
//        // $maxNrOfPlaceColumnsPerPoule = $this->getMaxNrOfPlaceColumnsPerPoule(count($poules), $width);
//        $maxNrOfPlaceColumnsPerPoule = $this->maxNrOfPoulePlaceColumns;
//        if ($this->enableOutOfBoundsException && $this->maxNrOfPoulePlaceColumns < 1) {
//            throw new PdfOutOfBoundsException('X', E_ERROR);
//        }
//
//        $xStart = $x;
//        while (count($poules) > 0) {
//            $poulesForLine = $this->reduceForLine($poules, $maxNrOfPlaceColumnsPerPoule, $width);
//            $x = $this->getXForCentered($poulesForLine, $width, $xStart, $maxNrOfPlaceColumnsPerPoule);
//            foreach ($poulesForLine as $poule) {
//                $nrOfPlaceColumnsPerPoule = $this->maxNrOfPoulePlaceColumns; // $this->getNrOfPlaceColumns($poule, $maxNrOfPlaceColumnsPerPoule);
//                $pouleWidth = $nrOfPlaceColumnsPerPoule * self::PlaceWidth;
//                $this->drawPoule($poule, $x, $y, $nrOfPlaceColumnsPerPoule);
//                $x += $pouleWidth + self::PouleMargin;
//            }
//            $y -= ($this->getMaxHeight($poulesForLine, $maxNrOfPlaceColumnsPerPoule) + self::PouleMargin);
//        }
//        return $y;
//    }
//
//    /**
//     * @param list<Poule> $poules
//     * @param int $maxNrOfPlaceColumnsPerPoule
//     * @param float $width
//     * @return list<Poule>
//     */
//    protected function reduceForLine(array &$poules, int $maxNrOfPlaceColumnsPerPoule, float $width): array
//    {
//        $x = 0;
//        $poulesForLine = [];
//        while (count($poules) > 0) {
//            $poule = array_shift($poules);
//            $poulesForLine[] = $poule;
//            $nrOfPlaceColumnsPerPoule = $this->maxNrOfPoulePlaceColumns; // $this->getNrOfPlaceColumns($poule, $maxNrOfPlaceColumnsPerPoule);
//            $pouleWidth = $nrOfPlaceColumnsPerPoule * self::PlaceWidth;
//
//            $x += $pouleWidth + self::PouleMargin;
//            if (($x + $pouleWidth) > $width) {
//                break;
//            }
//        }
//        return $poulesForLine;
//    }
//
//    /**
//     * @param list<Poule> $poulesForLine
//     * @param float $width
//     * @param float $x
//     * @param int $maxNrOfPlaceColumnsPerPoule
//     * @return float
//     */
//    protected function getXForCentered(array $poulesForLine, float $width, float $x, int $maxNrOfPlaceColumnsPerPoule): float
//    {
//        $widthPoules = 0;
//        foreach ($poulesForLine as $poule) {
//            if ($widthPoules > 0) {
//                $widthPoules += self::PouleMargin;
//            }
//            $nrOfPlaceColumnsPerPoule = $this->maxNrOfPoulePlaceColumns; // $this->getNrOfPlaceColumns($poule, $maxNrOfPlaceColumnsPerPoule);
//            $widthPoule = $nrOfPlaceColumnsPerPoule * self::PlaceWidth;
//            $widthPoules += $widthPoule;
//        }
//        return $x + (($width - $widthPoules) / 2);
//    }
//
//    /**
//     * @param list<Poule> $poulesForLine
//     * @param int $maxNrOfPlaceColumnsPerPoule
//     * @return int
//     */
//    protected function getMaxHeight(array $poulesForLine, int $maxNrOfPlaceColumnsPerPoule): int
//    {
//        $maxHeight = 0;
//        foreach ($poulesForLine as $poule) {
//            $nrOfPlaceColumnsPerPoule = $this->maxNrOfPoulePlaceColumns; // $this->getNrOfPlaceColumns($poule, $maxNrOfPlaceColumnsPerPoule);
//            $heightPoule = (int)ceil($poule->getPlaces()->count() / $nrOfPlaceColumnsPerPoule);
//            if ($heightPoule > $maxHeight) {
//                $maxHeight = $heightPoule;
//            }
//        }
//        return (1 + $maxHeight) * self::RowHeight;
//    }
}
