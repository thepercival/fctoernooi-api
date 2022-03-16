<?php

declare(strict_types=1);

namespace App\Export\Pdf\Structure;

use App\Export\Pdf\Page\Planning as PlanningPage;
use App\Export\Pdf\Page\Structure as StructurePage;
use App\Export\Pdf\Point;
use Doctrine\Common\Collections\Collection;
use Sports\NameService;
use Sports\Poule;
use Sports\Round;
use Sports\Round\Number as RoundNumber;
use Sports\Structure;
use Zend_Pdf_Page;

final class DimensionCalculator
{
    protected float $aspectRatio;
    protected NameService $nameService;
    private int $rowHeight;
    private int $fontHeight;
    private int $roundMargin;
    private int $pouleMargin;
    private int $placeWidth;
//    public const PADDING = 1;
//    public const BORDER = 1;
//    protected const PLACEWIDTH = 3;
//    protected const HORPLACEWIDTH = 3;
//    public const QUALIFYGROUPHEIGHT = 3;
    private float $pageMargin;

    public function __construct(protected StructurePage $defaultPage)
    {
        $this->nameService = new NameService();
        $this->aspectRatio = 1 / sqrt(2); // portret
        $this->pageMargin = $defaultPage->getPageMargin();
        $this->rowHeight = $defaultPage->getRowHeight();
        $this->fontHeight = $defaultPage->getFontHeight();
        $this->roundMargin = $defaultPage->getRoundMargin();
        $this->pouleMargin = $defaultPage->getPouleMargin();
        $this->placeWidth = $defaultPage->getPlaceWidth();
    }

    public function getMinimalDimensions(Point $dimensions): Point
    {
        if ($dimensions->getX() > $dimensions->getY()) {
            $layout = Zend_Pdf_Page::SIZE_A4_LANDSCAPE;
        } else {
            $layout = Zend_Pdf_Page::SIZE_A4;
        }
        $a4Dimensions = $this->convertZendLayoutToPoint($layout);

        if ($dimensions->getX() < $a4Dimensions->getX()) {
            $enlarged = $dimensions->enlarge($a4Dimensions->getX() / $dimensions->getX());
            return $enlarged; // $this->getEnlargedDimensions($enlarged);
        }
        return $dimensions;
    }

    protected function convertZendLayoutToPoint(string $layout): Point
    {
        $page = new PlanningPage($this->defaultPage->getParent(), $layout);
        return new Point($page->getWidth(), $page->getHeight());
    }


    public function getDimensions(
        Structure $structure,
        int &$maxNrOfPoulePlaceColumns,
        int|null $biggestNrOfPoulePlaces = null
    ): Point {
        if ($biggestNrOfPoulePlaces === null) {
            $biggestNrOfPoulePlaces = $this->getBiggestNrOfPoulePlaces($structure);
        }

        $width = $this->getPageWidth($structure, $maxNrOfPoulePlaceColumns);
        $height = $this->getPageHeight($structure, $maxNrOfPoulePlaceColumns);
        $maxHeight = $width / $this->aspectRatio;
        $minHeight = 842;
        if ($height > $maxHeight && $height > $minHeight) {
            if ($maxNrOfPoulePlaceColumns === $biggestNrOfPoulePlaces) { // het breedste geprobeerd
                $width = $height * $this->aspectRatio;
                return new Point($width, $height);
            }
            $maxNrOfPoulePlaceColumns++;
            return $this->getDimensions(
                $structure,
                $maxNrOfPoulePlaceColumns,
                $biggestNrOfPoulePlaces
            );
        } elseif ($height < ($maxHeight / 2)) {
            return new Point($width, $width * $this->aspectRatio);
        }

        return new Point($width, $width / $this->aspectRatio);
    }

    private function getBiggestNrOfPoulePlaces(Structure $structure): int
    {
        $roundNumber = $structure->getFirstRoundNumber();
        $max = $this->getBiggestNrOfPoulePlacesForRoundNumber($roundNumber);
        while ($roundNumber !== null) {
            $nextMax = $this->getBiggestNrOfPoulePlacesForRoundNumber($roundNumber);;
            if ($nextMax > $max) {
                $max = $nextMax;
            }
            $roundNumber = $roundNumber->getNext();
        }
        return $max;
    }

    private function getBiggestNrOfPoulePlacesForRoundNumber(RoundNumber $roundNumber): int
    {
        $max = 0;
        foreach ($roundNumber->getPoules() as $poule) {
            $nrOfPlaces = count($poule->getPlaces());
            if ($nrOfPlaces > $max) {
                $max = $nrOfPlaces;
            }
        }
        return $max;
    }

    private function getPageWidth(Structure $structure, int $maxNrOfPoulePlaceColumns): float
    {
        return $this->pageMargin
            + $this->getMaxRoundsWidth($structure->getRootRound(), $maxNrOfPoulePlaceColumns)
            + $this->pageMargin;
    }

    private function getMaxRoundsWidth(Round $round, int $maxNrOfPoulePlaceColumns): float
    {
        $roundWidth = $this->getRoundWidth($round, $maxNrOfPoulePlaceColumns);

        $childrenWidth = 0;
        foreach ($round->getChildren() as $childRound) {
            $childrenWidth += $this->getMaxRoundsWidth($childRound, $maxNrOfPoulePlaceColumns);
            $childrenWidth += $this->roundMargin;
        }
        $childrenWidth -= $this->roundMargin;

        return $childrenWidth > $roundWidth ? $childrenWidth : $roundWidth;
    }

    private function getRoundWidth(Round $round, int $maxNrOfPoulePlaceColumns): float
    {
        $width = $this->pouleMargin;
        foreach ($round->getPoules() as $poule) {
            $width += $this->getPouleWidth($poule, $maxNrOfPoulePlaceColumns);
            $width += $this->pouleMargin;
        }
        return $width;
    }

    public function getPouleWidth(Poule $poule, int $maxNrOfPoulePlaceColumns): int
    {
        $nrOfPoulePlaceColumns = count($poule->getPlaces());
        $nrOfColumns = $nrOfPoulePlaceColumns > $maxNrOfPoulePlaceColumns ? $maxNrOfPoulePlaceColumns : $nrOfPoulePlaceColumns;
        return $nrOfColumns * $this->placeWidth;
    }

    private function getPageHeight(Structure $structure, int $maxNrOfPoulePlaceColumns): float
    {
        $y = $this->defaultPage->drawHeader('tmp');
        $headerHeight = -$this->defaultPage->drawSubHeader('tmp', $y);
        return $headerHeight
            + $this->getMaxChildrenHeight([$structure->getRootRound()], $maxNrOfPoulePlaceColumns)
            + $this->pageMargin;
    }

    /**
     * @param list<Round> $children
     * @return float
     */
    private function getMaxChildrenHeight(array $children, int $maxNrOfPoulePlaceColumns): float
    {
        $maxHeight = 0;
        foreach ($children as $childRound) {
            $roundHeight = $this->getCumulativeRoundHeight($childRound, $maxNrOfPoulePlaceColumns);
            if ($roundHeight > $maxHeight) {
                $maxHeight = $roundHeight;
            }
        }
        return $maxHeight;
    }

    private function getCumulativeRoundHeight(Round $round, int $maxNrOfPoulePlaceColumns): float
    {
        $roundHeight = $this->getRoundHeight($round, $maxNrOfPoulePlaceColumns);
        $maxChildHeight = $this->getMaxChildrenHeight($round->getChildren(), $maxNrOfPoulePlaceColumns);
        return $roundHeight + $this->rowHeight + $maxChildHeight;
    }


//    public function getStructureHeight(Structure $structure): int
//    {
//        $height = 0;
//        $roundNumber = $structure->getFirstRoundNumber();
//        while ($roundNumber !== null) {
//            $height += $this->getRoundNumberHeight($roundNumber);
//            $nextRoundNumber = $roundNumber->getNext();
//            if ($nextRoundNumber !== null) {
//                $height += self::QUALIFYGROUPHEIGHT;
//            }
//        }
//        return $height;
//    }
//
//    public function getStructureWidth(Structure $structure): int
//    {
//        $maxWidth = 0;
//        $roundNumber = $structure->getFirstRoundNumber();
//        while ($roundNumber !== null) {
//            $width = $this->getRoundNumberWidth($roundNumber);
//            if ($width > $maxWidth) {
//                $maxWidth = $width;
//            }
//            $roundNumber = $roundNumber->getNext();
//        }
//        return $maxWidth;
//    }
//
//    public function getRoundNumberHeight(RoundNumber $roundNumber): int
//    {
//        $biggestPoule = $roundNumber->createPouleStructure()->getBiggestPoule();
//
//        $pouleNameHeight = 1;
//        $seperatorHeight = 1;
//        $height = self::BORDER + $pouleNameHeight + $seperatorHeight + $biggestPoule + self::BORDER;
//
//        return $height;
//    }
//
//    public function getRoundNumberWidth(RoundNumber $roundNumber): int
//    {
//        $rounds = $roundNumber->getRounds();
//        $width = 0;
//        foreach ($rounds as $round) {
//            $width += $this->getRoundWidth($round) + self::PADDING;
//        }
//        return $width - self::PADDING;
//    }
//
//    public function getRoundWidth(Round $round): int
//    {
//        $widthPoules = self::BORDER + self::PADDING
//            + $this->getAllPoulesWidth($round)
//            + self::PADDING + self::BORDER;
//
//        $qualifyGroups = $round->getQualifyGroups();
//        $widthQualifyGroups = 0;
//        foreach ($qualifyGroups as $qualifyGroup) {
//            $widthQualifyGroups += $this->getRoundWidth($qualifyGroup->getChildRound()) + self::PADDING;
//        }
//        $widthQualifyGroups -= self::PADDING;
//
//        return $widthPoules > $widthQualifyGroups ? $widthPoules : $widthQualifyGroups;
//    }
//
//    public function getQualifyGroupsWidth(Round $parentRound): int
//    {
//        $qualifyGroups = $parentRound->getQualifyGroups();
//        $widthQualifyGroups = 0;
//        foreach ($qualifyGroups as $qualifyGroup) {
//            $widthQualifyGroups += $this->getRoundWidth($qualifyGroup->getChildRound()) + RangeCalculator::PADDING;
//        }
//        return $widthQualifyGroups - RangeCalculator::PADDING;
//    }
//
//    public function getAllPoulesWidth(Round $round): int
//    {
//        $width = 0;
//        foreach ($round->getPoules() as $poule) {
//            $width += $this->getPouleWidth($poule) + self::PADDING;
//        }
//        $horPouleWidth = $this->getHorPoulesWidth($round);
//        if ($horPouleWidth === 0) {
//            return $width;
//        }
//        return $width + RangeCalculator::PADDING + $horPouleWidth;
//    }
//

//
//    public function getHorPoulesWidth(Round $round): int
//    {
//        if ($round->getHorizontalPoules(QualifyTarget::Winners)->count() === 0
//            && $round->getHorizontalPoules(QualifyTarget::Losers)->count() === 0) {
//            return 0;
//        }
//        return RangeCalculator::BORDER + RangeCalculator::PADDING + RangeCalculator::HORPLACEWIDTH;
//    }

    private function getRoundHeight(Round $round, int $maxNrOfPoulePlaceColumns): float
    {
        $height = $this->rowHeight; // title
        $height += $this->rowHeight; // poule title
        $nrOfPouleRows = $this->getMaxNrOfPoulePlaceRows($round->getPoules(), $maxNrOfPoulePlaceColumns);
        return $height + ($nrOfPouleRows * $this->rowHeight);
    }

    /**
     * @param Collection<int|string, Poule> $poules
     * @param int $maxNrOfPoulePlaceColumns
     * @return float
     */
    private function getMaxNrOfPoulePlaceRows(Collection $poules, int $maxNrOfPoulePlaceColumns): float
    {
        $maxNrOfRows = 0;
        foreach ($poules as $poule) {
            $nrOfRows = ceil(count($poule->getPlaces()) / $maxNrOfPoulePlaceColumns);
            if ($nrOfRows > $maxNrOfRows) {
                $maxNrOfRows = $nrOfRows;
            }
        }
        return $maxNrOfRows;
    }
}
