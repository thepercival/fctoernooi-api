<?php
declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document;
use App\Export\Pdf\Page as ToernooiPdfPage;
use Sports\Round;

class Grouping extends ToernooiPdfPage
{
    protected int $maxPoulesPerLine = 3;
    protected float $rowHeight = 18;

    public function __construct(Document $document, mixed $param1)
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

    public function draw(): void
    {
        $rooRound = $this->getParent()->getStructure()->getRootRound();
        $y = $this->drawHeader("indeling");
        $this->drawGrouping($rooRound, $y);
    }

    public function drawGrouping(Round $round, float $y): float
    {
        $y = $this->drawSubHeader("Indeling", $y);
        $nRowHeight = $this->getRowHeight();
        $fontHeight = $nRowHeight - 4;
        $pouleMargin = 20;
        $poules = $round->getPoules()->toArray();
        $nrOfPoules = $round->getPoules()->count();
        $percNumberWidth = 0.1;
        $nameService = $this->getParent()->getNameService();
        $yPouleStart = $y;
        $maxNrOfPlacesPerPoule = $round->getPoule(1)->getPlaces()->count();
        $nrOfLines = $this->getNrOfLines($nrOfPoules);
        for ($line = 1; $line <= $nrOfLines; $line++) {
            $nrOfPoulesForLine = $this->getNrOfPoulesForLine($nrOfPoules, $line === $nrOfLines);
            $pouleWidth = $this->getPouleWidth($nrOfPoulesForLine, $pouleMargin);
            $x = $this->getXLineCentered($nrOfPoulesForLine, $pouleWidth, $pouleMargin);
            while ($nrOfPoulesForLine > 0) {
                $poule = array_shift($poules);

                $numberWidth = $pouleWidth * $percNumberWidth;
                $this->setFont($this->getParent()->getFont(true), $fontHeight);
                $this->drawCell(
                    $this->getParent()->getNameService()->getPouleName($poule, true),
                    $x,
                    $yPouleStart,
                    $pouleWidth,
                    $nRowHeight,
                    Align::Center,
                    "black"
                );
                $this->setFont($this->getParent()->getFont(), $fontHeight);
                $y = $yPouleStart - $nRowHeight;
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
                    if ($this->getParent()->getPlaceLocationMap()->getCompetitor($place) !== null) {
                        $name = $nameService->getPlaceName($place, true);
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
                $x += $pouleMargin + $pouleWidth;
                $nrOfPoulesForLine--;
            }
            $yPouleStart -= ($maxNrOfPlacesPerPoule + 2 /* header + empty line */) * $nRowHeight;
        }
        return $y - (2 * $nRowHeight);
    }

    protected function getNrOfLines(int $nrOfPoules): int
    {
        if (($nrOfPoules % 3) !== 0) {
            $nrOfPoules += (3 - ($nrOfPoules % 3));
        }
        return (int)($nrOfPoules / 3);
    }

    protected function getNrOfPoulesForLine(int $nrOfPoules, bool $lastLine): int
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
