<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Document;
use App\Export\Pdf\Page as ToernooiPdfPage;
use Sports\Round;
use Sports\Poule;
use Sports\NameService;

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

    protected function getRowHeight(): float
    {
        return $this->rowHeight;
    }

    public function draw()
    {
        $rooRound = $this->getParent()->getStructure()->getRootRound();
        $nY = $this->drawHeader("indeling");
        $nY = $this->drawGrouping($rooRound, $nY);
    }

    public function drawGrouping(Round $round, float $nY): float
    {
        $nY = $this->drawSubHeader("Indeling", $nY);
        $nRowHeight = $this->getRowHeight();
        $fontHeight = $nRowHeight - 4;
        $pouleMargin = 20;
        $poules = $round->getPoules()->toArray();
        $nrOfPoules = $round->getPoules()->count();
        $percNumberWidth = 0.1;
        $nameService = $this->getParent()->getNameService();
        $nYPouleStart = $nY;
        $maxNrOfPlacesPerPoule = null;
        $nrOfLines = $this->getNrOfLines($nrOfPoules);
        for ($line = 1; $line <= $nrOfLines; $line++) {
            $nrOfPoulesForLine = $this->getNrOfPoulesForLine($nrOfPoules, $line === $nrOfLines);
            $pouleWidth = $this->getPouleWidth($nrOfPoulesForLine, $pouleMargin);
            $nX = $this->getXLineCentered($nrOfPoulesForLine, $pouleWidth, $pouleMargin);
            while ($nrOfPoulesForLine > 0) {
                $poule = array_shift($poules);

                if ($maxNrOfPlacesPerPoule === null) {
                    $maxNrOfPlacesPerPoule = $poule->getPlaces()->count();
                }
                $numberWidth = $pouleWidth * $percNumberWidth;
                $this->setFont($this->getParent()->getFont(true), $fontHeight);
                $this->drawCell(
                    $this->getParent()->getNameService()->getPouleName($poule, true),
                    $nX,
                    $nYPouleStart,
                    $pouleWidth,
                    $nRowHeight,
                    ToernooiPdfPage::ALIGNCENTER,
                    "black"
                );
                $this->setFont($this->getParent()->getFont(), $fontHeight);
                $nY = $nYPouleStart - $nRowHeight;
                foreach ($poule->getPlaces() as $place) {
                    $this->drawCell(
                        $place->getNumber(),
                        $nX,
                        $nY,
                        $numberWidth,
                        $nRowHeight,
                        ToernooiPdfPage::ALIGNRIGHT,
                        "black"
                    );
                    $name = null;
                    if( $this->getParent()->getPlaceLocationMap()->getCompetitor( $place ) !== null ) {
                       $name = $nameService->getPlaceName($place, true);
                    }
                    $this->drawCell(
                        $name,
                        $nX + $numberWidth,
                        $nY,
                        $pouleWidth - $numberWidth,
                        $nRowHeight,
                        ToernooiPdfPage::ALIGNLEFT,
                        "black"
                    );
                    $nY -= $nRowHeight;
                }
                $nX += $pouleMargin + $pouleWidth;
                $nrOfPoulesForLine--;
            }
            $nYPouleStart -= ($maxNrOfPlacesPerPoule + 2 /* header + empty line */) * $nRowHeight;
        }
        return $nY - (2 * $nRowHeight);
    }

    protected function getNrOfLines(int $nrOfPoules): int
    {
        if (($nrOfPoules % 3) !== 0) {
            $nrOfPoules += (3 - ($nrOfPoules % 3));
        }
        return ($nrOfPoules / 3);
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
