<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document\Structure as StructureDocument;
use App\Export\Pdf\Page as ToernooiPdfPage;
use Sports\Poule;

/**
 * @template-extends ToernooiPdfPage<StructureDocument>
 */
class Grouping extends ToernooiPdfPage
{
    protected int $maxPoulesPerLine = 3;
    protected float $rowHeight = 18;

    public function __construct(StructureDocument $document, mixed $param1)
    {
        parent::__construct($document, $param1);
        $this->setLineWidth(0.5);
    }

    public function getPageMargin(): float
    {
        return 20;
    }

    public function getHeaderHeight(): float
    {
        return 0;
    }

    public function getRowHeight(): float
    {
        return $this->rowHeight;
    }

    /**
     * @param list<Poule> $poules
     */
    public function draw(array &$poules): void
    {
        $y = $this->drawHeader("indeling");
        $this->drawGrouping($poules, $y);
    }

    /**
     * @param list<Poule> $poules
     */
    public function drawGrouping(array &$poules, float $y): void
    {
        $y = $this->drawSubHeader("Indeling", $y);

        $nrOfPoules = count($poules);
        $yPouleStart = $y;
        $rowHeight = $this->getRowHeight();

        $nrOfPouleRows = $this->getNrOfPouleRows($nrOfPoules);
        for ($pouleRowNr = 1; $pouleRowNr <= $nrOfPouleRows; $pouleRowNr++) {
            $nrOfPoulesForRow = $this->getNrOfPoulesForRow($nrOfPoules, $pouleRowNr === $nrOfPouleRows);
            $pouleRowHeight = $this->getPouleRowHeight($poules, $nrOfPoulesForRow);
            $yPouleEnd = $yPouleStart - $pouleRowHeight;
            if ($yPouleStart !== $y && $yPouleEnd < $this->getPageMargin()) {
                break;
            }
            $yPouleStart = $this->drawPouleRow($poules, $nrOfPoulesForRow, $yPouleStart);
            $yPouleStart -= $rowHeight;
        }
    }

    /**
     * @param list<Poule> $poules
     * @param int $nrOfPoulesForRow
     * @param float $y
     * @return float
     */
    protected function drawPouleRow(array &$poules, int $nrOfPoulesForRow, float $y): float
    {
        $pouleMargin = 20;
        $pouleWidth = $this->getPouleWidth($nrOfPoulesForRow, $pouleMargin);
        $x = $this->getXLineCentered($nrOfPoulesForRow, $pouleWidth, $pouleMargin);
        $lowestY = 0;

        while ($nrOfPoulesForRow > 0) {
            $poule = array_shift($poules);
            if ($poule === null) {
                break;
            }
            $yEnd = $this->drawPoule($poule, $x, $pouleWidth, $y);
            if ($lowestY === 0 || $yEnd < $lowestY) {
                $lowestY = $yEnd;
            }
            $x += $pouleMargin + $pouleWidth;

            $nrOfPoulesForRow--;
        }
        return $lowestY;
    }

    protected function drawPoule(Poule $poule, float $x, float $pouleWidth, float $yStart): float
    {
        $nRowHeight = $this->getRowHeight();
        $fontHeight = $nRowHeight - 4;

        $numberWidth = $pouleWidth * 0.1;
        $this->setFont($this->parent->getFont(true), $fontHeight);
        $this->drawCell(
            $this->parent->getNameService()->getPouleName($poule, true),
            $x,
            $yStart,
            $pouleWidth,
            $nRowHeight,
            Align::Center,
            "black"
        );
        $this->setFont($this->parent->getFont(), $fontHeight);
        $y = $yStart - $nRowHeight;
        foreach ($poule->getPlaces() as $place) {
            $this->drawCell(
                (string)$place->getPlaceNr(),
                $x,
                $y,
                $numberWidth,
                $nRowHeight,
                Align::Right,
                "black"
            );
            $name = '';
            if ($this->parent->getPlaceLocationMap()->getCompetitor($place) !== null) {
                $name = $this->parent->getNameService()->getPlaceName($place, true);
            }
            $this->drawCell(
                $name,
                $x + $numberWidth,
                $y,
                $pouleWidth - $numberWidth,
                $nRowHeight,
                Align::Left,
                "black"
            );
            $y -= $nRowHeight;
        }
        return $y;
    }

    protected function getNrOfPouleRows(int $nrOfPoules): int
    {
        if (($nrOfPoules % 3) !== 0) {
            $nrOfPoules += (3 - ($nrOfPoules % 3));
        }
        return (int)($nrOfPoules / 3);
    }

    /**
     * @param list<Poule> $poules
     * @param int $nrOfPoulesForRow
     * @return float
     */
    protected function getPouleRowHeight(array $poules, int $nrOfPoulesForRow): float
    {
        $maxPouleHeight = 0;
        for ($pouleNr = 1; $pouleNr <= $nrOfPoulesForRow; $pouleNr++) {
            $poule = array_shift($poules);
            if ($poule === null) {
                continue;
            }
            $pouleHeight = $this->getPouleHeight($poule);
            if ($pouleHeight > $maxPouleHeight) {
                $maxPouleHeight = $pouleHeight;
            }
        }
        return $maxPouleHeight;
    }

    protected function getPouleHeight(Poule $poule): float
    {
        $nRowHeight = $this->getRowHeight();
        return $nRowHeight + (count($poule->getPlaces()) * $nRowHeight);
    }

    protected function getNrOfPoulesForRow(int $nrOfPoules, bool $lastLine): int
    {
        if ($nrOfPoules === 4) {
            return 2;
        }
        if ($nrOfPoules <= 3) {
            return $nrOfPoules;
        }
        if (!$lastLine) {
            return 3;
        }
        if (($nrOfPoules % 3) === 0) {
            return 3;
        }
        return ($nrOfPoules % 3);
    }

    protected function getPouleWidth(int $nrOfPoules, float $margin): float
    {
        if ($nrOfPoules === 1) {
            $nrOfPoules++;
        }
        return ($this->getDisplayWidth() - (($nrOfPoules - 1) * $margin)) / $nrOfPoules;
    }

    /**
     * maximaal 4 poules in de breedte
     */
    protected function getXLineCentered(int $nrOfPoules, float $pouleWidth, float $margin): float
    {
        if ($nrOfPoules > $this->maxPoulesPerLine) {
            $nrOfPoules = $this->maxPoulesPerLine;
        }
        $width = ($nrOfPoules * $pouleWidth) + (($nrOfPoules - 1) * $margin);
        return $this->getPageMargin() + ($this->getDisplayWidth() - $width) / 2;
    }
}
