<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Exceptions\PdfOutOfBoundsException;
use App\Export\Pdf\Align;
use App\Export\Pdf\Document\Structure as StructureDocument;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Point;
use Sports\Place;
use Sports\Poule;
use Sports\Round;

/**
 * @template-extends ToernooiPdfPage<StructureDocument>
 */
class Structure extends ToernooiPdfPage
{
    public const RowHeight = 18;
    public const FontHeight = self::RowHeight - 4;
    public const RoundMargin = 10;
    public const PouleMargin = 10;
    public const PlaceWidth = 30;

    private bool $enableOutOfBoundsException;
    private int $maxNrOfPoulePlaceColumns = 1;

    public function __construct(StructureDocument $document, Point $point, bool $enableOutOfBoundsException)
    {
        $dimensions = $point->getX() . ':' . $point->getY();
        parent::__construct($document, $dimensions);
        $this->setLineWidth(0.5);
        $this->enableOutOfBoundsException = $enableOutOfBoundsException;
    }

//    public function getParent(): StructureDocument
//    {
//        return $this->parent;
//    }

    public function getPageMargin(): float
    {
        return 20;
    }

    public function getHeaderHeight(): float
    {
        return 0;
    }

    public function getRowHeight(): int
    {
        return self::RowHeight;
    }

    public function getFontHeight(): int
    {
        return self::FontHeight;
    }

    public function getRoundMargin(): int
    {
        return self::RoundMargin;
    }

    public function getPouleMargin(): int
    {
        return self::PouleMargin;
    }

    public function getPlaceWidth(): int
    {
        return self::PlaceWidth;
    }

    public function setMaxNrOfPoulePlaceColumns(int $maxNrOfPoulePlaceColumns): void
    {
        $this->maxNrOfPoulePlaceColumns = $maxNrOfPoulePlaceColumns;
    }

    public function draw(): void
    {
        foreach ($this->parent->getStructure()->getCategories() as $category) {
            $rooRound = $category->getRootRound();
            $y = $this->drawHeader('opzet');
            $y = $this->drawSubHeader('Opzet', $y);
            $this->drawRound($rooRound, $y, $this->getPageMargin(), $this->getDisplayWidth());
        }
    }

    protected function drawRound(Round $round, float $y, float $x, float $width): void
    {
//        if ($this->enableOutOfBoundsException && $width < ((self::PlaceWidth * 2) + self::PouleMargin)) {
//            throw new PdfOutOfBoundsException('X', E_ERROR);
//        }

        $this->setFont($this->parent->getFont(true), self::FontHeight);

        $arrLineColors = !$round->isRoot() ? ['t' => 'black'] : null;
        $roundName = $this->parent->getNameService()->getRoundName($round);
        $this->drawCell($roundName, $x, $y, $width, self::RowHeight, Align::Center, $arrLineColors);
        $y -= self::RowHeight;

        if ($round->getPoules()->count() === 1 && $round->getPoule(1)->getPlaces()->count() < 3) {
            return;
        }
        $this->setFont($this->parent->getFont(), self::FontHeight);

        $poules = array_values($round->getPoules()->toArray());
        $y = $this->drawPoules($poules, $x, $y, $width);

        $nrOfChildren = count($round->getChildren());
        if ($nrOfChildren === 0) {
            return;
        }

        $widthRoundMargins = (count($round->getChildren()) - 1) * self::RoundMargin;
        $width -= $widthRoundMargins;
        foreach ($round->getChildren() as $childRound) {
            $widthChild = $childRound->getNrOfPlaces() / $round->getNrOfPlacesChildren() * $width;
            $this->drawRound($childRound, $y, $x, $widthChild);
            $x += $widthChild + self::RoundMargin;
        }
    }

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

    /**
     * @param list<Poule> $poules
     * @param float $x
     * @param float $y
     * @param float $width
     * @return float
     * @throws PdfOutOfBoundsException
     */
    protected function drawPoules(array $poules, float $x, float $y, float $width): float
    {
        // $maxNrOfPlaceColumnsPerPoule = $this->getMaxNrOfPlaceColumnsPerPoule(count($poules), $width);
        $maxNrOfPlaceColumnsPerPoule = $this->maxNrOfPoulePlaceColumns;
        if ($this->enableOutOfBoundsException && $this->maxNrOfPoulePlaceColumns < 1) {
            throw new PdfOutOfBoundsException('X', E_ERROR);
        }

        $xStart = $x;
        while (count($poules) > 0) {
            $poulesForLine = $this->reduceForLine($poules, $maxNrOfPlaceColumnsPerPoule, $width);
            $x = $this->getXForCentered($poulesForLine, $width, $xStart, $maxNrOfPlaceColumnsPerPoule);
            foreach ($poulesForLine as $poule) {
                $nrOfPlaceColumnsPerPoule = $this->maxNrOfPoulePlaceColumns; // $this->getNrOfPlaceColumns($poule, $maxNrOfPlaceColumnsPerPoule);
                $pouleWidth = $nrOfPlaceColumnsPerPoule * self::PlaceWidth;
                $this->drawPoule($poule, $x, $y, $nrOfPlaceColumnsPerPoule);
                $x += $pouleWidth + self::PouleMargin;
            }
            $y -= ($this->getMaxHeight($poulesForLine, $maxNrOfPlaceColumnsPerPoule) + self::PouleMargin);
        }
        return $y;
    }

    /**
     * @param list<Poule> $poules
     * @param int $maxNrOfPlaceColumnsPerPoule
     * @param float $width
     * @return list<Poule>
     */
    protected function reduceForLine(array &$poules, int $maxNrOfPlaceColumnsPerPoule, float $width): array
    {
        $x = 0;
        $poulesForLine = [];
        while (count($poules) > 0) {
            $poule = array_shift($poules);
            $poulesForLine[] = $poule;
            $nrOfPlaceColumnsPerPoule = $this->maxNrOfPoulePlaceColumns; // $this->getNrOfPlaceColumns($poule, $maxNrOfPlaceColumnsPerPoule);
            $pouleWidth = $nrOfPlaceColumnsPerPoule * self::PlaceWidth;

            $x += $pouleWidth + self::PouleMargin;
            if (($x + $pouleWidth) > $width) {
                break;
            }
        }
        return $poulesForLine;
    }

    protected function drawPoule(Poule $poule, float $x, float $y, int $nrOfPlaceColumns): void
    {
        $pouleWidth = $nrOfPlaceColumns * self::PlaceWidth;
        $pouleName = $this->parent->getNameService()->getPouleName($poule, $nrOfPlaceColumns > 1);
        $this->setFont($this->parent->getFont(true), self::FontHeight);
        $this->drawCell(
            $pouleName,
            $x,
            $y,
            $pouleWidth,
            self::RowHeight,
            Align::Center,
            'black'
        );
        $this->setFont($this->parent->getFont(), self::FontHeight);
        $y -= self::RowHeight;
        $places = $poule->getPlaces()->toArray();
        uasort(
            $places,
            function (Place $placeA, Place $placeB) {
                return ($placeA->getPlaceNr() > $placeB->getPlaceNr()) ? 1 : -1;
            }
        );
        $xStart = $x;
        foreach ($places as $place) {
            $placeName = $this->parent->getNameService()->getPlaceFromName($place, false);
            $this->drawCell(
                $placeName,
                $x,
                $y,
                self::PlaceWidth,
                self::RowHeight,
                Align::Center,
                'black'
            );
            if (($place->getPlaceNr() % $nrOfPlaceColumns) === 0) { // to next line
                $x = $xStart;
                $y -= self::RowHeight;
            } else {
                $x += self::PlaceWidth;
            }
        }
    }

    /**
     * @param list<Poule> $poulesForLine
     * @param float $width
     * @param float $x
     * @param int $maxNrOfPlaceColumnsPerPoule
     * @return float
     */
    protected function getXForCentered(array $poulesForLine, float $width, float $x, int $maxNrOfPlaceColumnsPerPoule): float
    {
        $widthPoules = 0;
        foreach ($poulesForLine as $poule) {
            if ($widthPoules > 0) {
                $widthPoules += self::PouleMargin;
            }
            $nrOfPlaceColumnsPerPoule = $this->maxNrOfPoulePlaceColumns; // $this->getNrOfPlaceColumns($poule, $maxNrOfPlaceColumnsPerPoule);
            $widthPoule = $nrOfPlaceColumnsPerPoule * self::PlaceWidth;
            $widthPoules += $widthPoule;
        }
        return $x + (($width - $widthPoules) / 2);
    }

    /**
     * @param list<Poule> $poulesForLine
     * @param int $maxNrOfPlaceColumnsPerPoule
     * @return int
     */
    protected function getMaxHeight(array $poulesForLine, int $maxNrOfPlaceColumnsPerPoule): int
    {
        $maxHeight = 0;
        foreach ($poulesForLine as $poule) {
            $nrOfPlaceColumnsPerPoule = $this->maxNrOfPoulePlaceColumns; // $this->getNrOfPlaceColumns($poule, $maxNrOfPlaceColumnsPerPoule);
            $heightPoule = (int)ceil($poule->getPlaces()->count() / $nrOfPlaceColumnsPerPoule);
            if ($heightPoule > $maxHeight) {
                $maxHeight = $heightPoule;
            }
        }
        return (1 + $maxHeight) * self::RowHeight;
    }
}
