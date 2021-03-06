<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document;
use App\Export\Pdf\Page as ToernooiPdfPage;
use Sports\Place;
use Sports\Round;
use Sports\Poule;
use Sports\NameService;
use App\Exceptions\PdfOutOfBoundsException;

class Structure extends ToernooiPdfPage
{
    private const RowHeight = 18;
    private const FontHeight = self::RowHeight - 4;
    private const RoundMargin = 10;
    private const PouleMargin = 10;
    private const PlaceWidth = 30;

    /**
     * @var bool
     */
    private $enableOutOfBoundsException;

    public function __construct(Document $document, mixed $param1, bool $enableOutOfBoundsException)
    {
        parent::__construct($document, $param1, null, null);
        $this->setLineWidth(0.5);
        $this->enableOutOfBoundsException = $enableOutOfBoundsException;
    }

    public function getPageMargin(): float
    {
        return 20;
    }

    public function getHeaderHeight(): float
    {
        return 0;
    }

    public function draw(): void
    {
        $rooRound = $this->getParent()->getStructure()->getRootRound();
        $y = $this->drawHeader("opzet");
        $y = $this->drawSubHeader("Opzet", $y);
        $this->drawRound($rooRound, $y, $this->getPageMargin(), $this->getDisplayWidth());
    }

    protected function drawRound(Round $round, float $y, float $x, float $width): void
    {
        if ($this->enableOutOfBoundsException && $width < ((self::PlaceWidth * 2) + self::PouleMargin)) {
            throw new PdfOutOfBoundsException("X", E_ERROR);
        }

        $this->setFont($this->getParent()->getFont(true), self::FontHeight);

        $arrLineColors = !$round->isRoot() ? array("t" => "black") : null;
        $roundName = $this->getParent()->getNameService()->getRoundName($round);
        $this->drawCell($roundName, $x, $y, $width, self::RowHeight, Align::Center, $arrLineColors);
        $y -= self::RowHeight;

        if ($round->getPoules()->count() === 1 && $round->getPoule(1)->getPlaces()->count() < 3) {
            return;
        }
        $this->setFont($this->getParent()->getFont(), self::FontHeight);

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

    protected function getMaxNrOfPlaceColumnsPerPoule(int $nrOfPoules, float $width): int
    {
        $pouleMarginsWidth = ($nrOfPoules - 1) * self::PouleMargin;
        $pouleWidth = ($width - $pouleMarginsWidth) / $nrOfPoules;
        $maxNrOfPlaceColumnsPerPoule = (int)floor($pouleWidth / self::PlaceWidth);
        if( $maxNrOfPlaceColumnsPerPoule === 0 ) {
            $maxNrOfPlaceColumnsPerPoule = 1;
        }
        return $maxNrOfPlaceColumnsPerPoule;
    }

    protected function getNrOfPlaceColumns(Poule $poule, int $maxNrOfPlaceColumnsPerPoule): int
    {
        if ($maxNrOfPlaceColumnsPerPoule === 0) {
            $trrrr = 1;
        }
        $nrOfPlaceColumnsPerPoule = $poule->getPlaces()->count();
        if ($nrOfPlaceColumnsPerPoule > $maxNrOfPlaceColumnsPerPoule) {
            $nrOfPlaceColumnsPerPoule = $maxNrOfPlaceColumnsPerPoule;
        }
        return $nrOfPlaceColumnsPerPoule;
    }

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
        $maxNrOfPlaceColumnsPerPoule = $this->getMaxNrOfPlaceColumnsPerPoule(count($poules), $width);
        if ($this->enableOutOfBoundsException && $maxNrOfPlaceColumnsPerPoule < 1) {
            throw new PdfOutOfBoundsException("X", E_ERROR);
        }

        $xStart = $x;
        while (count($poules) > 0) {
            $poulesForLine = $this->reduceForLine($poules, $maxNrOfPlaceColumnsPerPoule, $width);
            $x = $this->getXForCentered($poulesForLine, $width, $xStart, $maxNrOfPlaceColumnsPerPoule);
            foreach ($poulesForLine as $poule) {
                $nrOfPlaceColumnsPerPoule = $this->getNrOfPlaceColumns($poule, $maxNrOfPlaceColumnsPerPoule);
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
            $nrOfPlaceColumnsPerPoule = $this->getNrOfPlaceColumns($poule, $maxNrOfPlaceColumnsPerPoule);
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
        $pouleName = $this->getParent()->getNameService()->getPouleName($poule, $nrOfPlaceColumns > 1);
        $this->drawCell(
            $pouleName,
            $x,
            $y,
            $pouleWidth,
            self::RowHeight,
            Align::Center,
            'black'
        );
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
            $placeName = $this->getParent()->getNameService()->getPlaceFromName($place, false);
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
            $nrOfPlaceColumnsPerPoule = $this->getNrOfPlaceColumns($poule, $maxNrOfPlaceColumnsPerPoule);
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
            $nrOfPlaceColumnsPerPoule = $this->getNrOfPlaceColumns($poule, $maxNrOfPlaceColumnsPerPoule);
            $heightPoule = (int)ceil($poule->getPlaces()->count() / $nrOfPlaceColumnsPerPoule);
            if ($heightPoule > $maxHeight) {
                $maxHeight = $heightPoule;
            }
        }
        return (1 + $maxHeight) * self::RowHeight;
    }
}
