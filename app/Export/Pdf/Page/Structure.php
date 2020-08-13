<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 15:03
 */

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Page as ToernooiPdfPage;
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

    public function __construct($param1, bool $enableOutOfBoundsException)
    {
        parent::__construct($param1);
        $this->setLineWidth(0.5);
        $this->enableOutOfBoundsException = $enableOutOfBoundsException;
    }

    public function getPageMargin()
    {
        return 20;
    }

    public function getHeaderHeight()
    {
        return 0;
    }

    public function draw()
    {
        $rooRound = $this->getParent()->getStructure()->getRootRound();
        $nY = $this->drawHeader("opzet");
        $nY = $this->drawSubHeader("Opzet", $nY);
        $this->drawRound($rooRound, $nY, $this->getPageMargin(), $this->getDisplayWidth());
    }

    protected function drawRound(Round $round, $nY, $nX, $width)
    {
        if ($this->enableOutOfBoundsException && $width < ((self::PlaceWidth * 2) + self::PouleMargin)) {
            throw new PdfOutOfBoundsException("X", E_ERROR);
        }

        $this->setFont($this->getParent()->getFont(true), self::FontHeight);

        $arrLineColors = !$round->isRoot() ? array("t" => "black") : null;
        $roundName = $this->getParent()->getNameService()->getRoundName($round);
        $this->drawCell($roundName, $nX, $nY, $width, self::RowHeight, ToernooiPdfPage::ALIGNCENTER, $arrLineColors);
        $nY -= self::RowHeight;

        if ($round->getPoules()->count() === 1 && $round->getPoules()->first()->getPlaces()->count() < 3) {
            return;
        }
        $this->setFont($this->getParent()->getFont(), self::FontHeight);

        $nY = $this->drawPoules($round->getPoules()->toArray(), $nX, $nY, $width);

        $nrOfChildren = count($round->getChildren());
        if ($nrOfChildren === 0) {
            return;
        }

        $widthRoundMargins = (count($round->getChildren()) - 1) * self::RoundMargin;
        $width -= $widthRoundMargins;
        foreach ($round->getChildren() as $childRound) {
            $widthChild = $childRound->getNrOfPlaces() / $round->getNrOfPlacesChildren() * $width;
            $this->drawRound($childRound, $nY, $nX, $widthChild);
            $nX += $widthChild + self::RoundMargin;
        }
    }

    protected function getMaxNrOfPlaceColumnsPerPoule(int $nrOfPoules, $width): int
    {
        $pouleMarginsWidth = ($nrOfPoules - 1) * self::PouleMargin;
        $pouleWidth = ($width - $pouleMarginsWidth) / $nrOfPoules;
        return (int)floor($pouleWidth / self::PlaceWidth);
    }

    protected function getNrOfPlaceColumns(Poule $poule, int $maxNrOfPlaceColumnsPerPoule): int
    {
        $nrOfPlaceColumnsPerPoule = $poule->getPlaces()->count();
        if ($nrOfPlaceColumnsPerPoule > $maxNrOfPlaceColumnsPerPoule) {
            $nrOfPlaceColumnsPerPoule = $maxNrOfPlaceColumnsPerPoule;
        }
        return $nrOfPlaceColumnsPerPoule;
    }

    protected function drawPoules(array $poules, $nX, $nY, $width)
    {
        $maxNrOfPlaceColumnsPerPoule = $this->getMaxNrOfPlaceColumnsPerPoule(count($poules), $width);
        if ($this->enableOutOfBoundsException && $maxNrOfPlaceColumnsPerPoule < 1) {
            throw new PdfOutOfBoundsException("X", E_ERROR);
        }

        $nXStart = $nX;
        while (count($poules) > 0) {
            $poulesForLine = $this->reduceForLine($poules, $maxNrOfPlaceColumnsPerPoule, $width);
            $nX = $this->getXForCentered($poulesForLine, $width, $nXStart, $maxNrOfPlaceColumnsPerPoule);
            foreach ($poulesForLine as $poule) {
                $nrOfPlaceColumnsPerPoule = $this->getNrOfPlaceColumns($poule, $maxNrOfPlaceColumnsPerPoule);
                $pouleWidth = $nrOfPlaceColumnsPerPoule * self::PlaceWidth;
                $this->drawPoule($poule, $nX, $nY, $nrOfPlaceColumnsPerPoule);
                $nX += $pouleWidth + self::PouleMargin;
            }
            $nY -= ($this->getMaxHeight($poulesForLine, $maxNrOfPlaceColumnsPerPoule) + self::PouleMargin);
        }
        return $nY;
    }


    protected function reduceForLine(array &$poules, int $maxNrOfPlaceColumnsPerPoule, $width): array
    {
        $nX = 0;
        $poulesForLine = [];
        while (count($poules) > 0) {
            $poule = array_shift($poules);
            $poulesForLine[] = $poule;
            $nrOfPlaceColumnsPerPoule = $this->getNrOfPlaceColumns($poule, $maxNrOfPlaceColumnsPerPoule);
            $pouleWidth = $nrOfPlaceColumnsPerPoule * self::PlaceWidth;

            $nX += $pouleWidth + self::PouleMargin;
            if (($nX + $pouleWidth) > $width) {
                break;
            }
        }
        return $poulesForLine;
    }

    protected function drawPoule(Poule $poule, $nX, $nY, int $nrOfPlaceColumns)
    {
        $pouleWidth = $nrOfPlaceColumns * self::PlaceWidth;
        $pouleName = $this->getParent()->getNameService()->getPouleName($poule, $nrOfPlaceColumns > 1);
        $this->drawCell(
            $pouleName,
            $nX,
            $nY,
            $pouleWidth,
            self::RowHeight,
            ToernooiPdfPage::ALIGNCENTER,
            "black"
        );
        $nY -= self::RowHeight;
        $places = $poule->getPlaces()->toArray();
        uasort(
            $places,
            function ($placeA, $placeB) {
                return ($placeA->getNumber() > $placeB->getNumber()) ? 1 : -1;
            }
        );
        $nXStart = $nX;
        foreach ($places as $place) {
            $placeName = $this->getParent()->getNameService()->getPlaceFromName($place, false);
            $this->drawCell(
                $placeName,
                $nX,
                $nY,
                self::PlaceWidth,
                self::RowHeight,
                ToernooiPdfPage::ALIGNCENTER,
                "black"
            );
            if (($place->getNumber() % $nrOfPlaceColumns) === 0) { // to next line
                $nX = $nXStart;
                $nY -= self::RowHeight;
            } else {
                $nX += self::PlaceWidth;
            }
        }
    }

    protected function getXForCentered(array $poulesForLine, $width, $nX, $maxNrOfPlaceColumnsPerPoule)
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
        return $nX + (($width - $widthPoules) / 2);
    }

    protected function getMaxHeight(array $poulesForLine, $maxNrOfPlaceColumnsPerPoule)
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
