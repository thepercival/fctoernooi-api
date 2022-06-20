<?php

declare(strict_types=1);

namespace App\Export\Pdf\Structure;

final class DimensionCalculator
{
//    protected NameService $nameService;
//    public const MAXNROFPLACEROWS = 10;

//    public function __construct(
//        private float $pageMargin,
//        private int $rowHeight,
//        private int $fontHeight,
//        private int $roundMargin,
//        private int $pouleMargin,
//        private int $placeWidth
//    )
//    {
//        $this->nameService = new NameService();
//    }

//    public function getDimensions(Round $rootRound): Point
//    {
//        $minimalWidth = $this->getMinimalRoundsWidth($rootRound->getChildren());
//        if( $minimalWidth < 595) {
//            $minimalWidth = 595;
//        }
//        return new Point($minimalWidth, $minimalWidth * sqrt(2) );
//    }
//
//    protected function getMinimalRoundsWidth(array $rounds): float
//    {
//        $minimalWidth = $this->roundMargin;
//        foreach( $rounds as $round ) {
//            if( count($round->getChildren()) === 1 ) {
//                return $this->getMinimalRoundWidth($round);
//            }
//            $minimalWidth += $this->getMinimalRoundsWidth( $round->getChildren() );
//            $minimalWidth += $this->roundMargin;
//        }
//        return $minimalWidth;
//    }
//
//    protected function getMinimalRoundWidth(Round $round): float
//    {
//        // gebruik de naam als graagmeter
//        // het kan ook zijn dat er veel places naast elkaar moeten
//        // in 1 poule, omdat er maar maximaal 10 places per poule onder elkaar mogen!!!!!!!!!!!!
//        $round->
//        $minimalWidth = $this->roundMargin;
//        foreach( $rounds as $round ) {
//            if( count($round->getChildren()) === 1 ) {
//                return 20; // fontheight * x karakters
//            }
//            $minimalWidth += $this->getMinimalRoundsWidth( $round->getChildren() );
//            $minimalWidth += $this->roundMargin;
//        }
//        return $minimalWidth;
//    }
//
//
//    public function getDimensions(
//        Structure $structure,
//        int &$maxNrOfPoulePlaceColumns,
//        int|null $biggestNrOfPoulePlaces = null
//    ): Point {
//        if ($biggestNrOfPoulePlaces === null) {
//            $biggestNrOfPoulePlaces = $this->getBiggestNrOfPoulePlaces($structure);
//        }
//
//        $width = $this->getPageWidth($structure, $maxNrOfPoulePlaceColumns);
//        $height = $this->getPageHeight($structure, $maxNrOfPoulePlaceColumns);
//        $maxHeight = $width / $this->aspectRatio;
//        $minHeight = 842;
//        if ($height > $maxHeight && $height > $minHeight) {
//            if ($maxNrOfPoulePlaceColumns === $biggestNrOfPoulePlaces) { // het breedste geprobeerd
//                $width = $height * $this->aspectRatio;
//                return new Point($width, $height);
//            }
//            $maxNrOfPoulePlaceColumns++;
//            return $this->getDimensions(
//                $structure,
//                $maxNrOfPoulePlaceColumns,
//                $biggestNrOfPoulePlaces
//            );
//        } elseif ($height < ($maxHeight / 2)) {
//            return new Point($width, $width * $this->aspectRatio);
//        }
//
//        return new Point($width, $width / $this->aspectRatio);
//    }
//
//    private function getBiggestNrOfPoulePlaces(Structure $structure): int
//    {
//        $roundNumber = $structure->getFirstRoundNumber();
//        $max = $this->getBiggestNrOfPoulePlacesForRoundNumber($roundNumber);
//        while ($roundNumber !== null) {
//            $nextMax = $this->getBiggestNrOfPoulePlacesForRoundNumber($roundNumber);
//            if ($nextMax > $max) {
//                $max = $nextMax;
//            }
//            $roundNumber = $roundNumber->getNext();
//        }
//        return $max;
//    }
//
//    private function getBiggestNrOfPoulePlacesForRoundNumber(RoundNumber $roundNumber): int
//    {
//        $max = 0;
//        foreach ($roundNumber->getPoules() as $poule) {
//            $nrOfPlaces = count($poule->getPlaces());
//            if ($nrOfPlaces > $max) {
//                $max = $nrOfPlaces;
//            }
//        }
//        return $max;
//    }
//
//    private function getPageWidth(Structure $structure, int $maxNrOfPoulePlaceColumns): float
//    {
//        $width = 0;
//        foreach ($structure->getCategories() as $category) {
//            if ($width > 0) {
//                $width += $this->pouleMargin;
//            }
//            $width += $this->getCategoryWidth($category, $maxNrOfPoulePlaceColumns);
//        }
//        return $width;
//    }
//
//    private function getCategoryWidth(Category $category, int $maxNrOfPoulePlaceColumns): float
//    {
//        return $this->pageMargin
//            + $this->getMaxRoundsWidth($category->getRootRound(), $maxNrOfPoulePlaceColumns)
//            + $this->pageMargin;
//    }
//
//
//    private function getMaxRoundsWidth(Round $round, int $maxNrOfPoulePlaceColumns): float
//    {
//        $roundWidth = $this->getRoundWidth($round, $maxNrOfPoulePlaceColumns);
//
//        $childrenWidth = 0;
//        foreach ($round->getChildren() as $childRound) {
//            $childrenWidth += $this->getMaxRoundsWidth($childRound, $maxNrOfPoulePlaceColumns);
//            $childrenWidth += $this->roundMargin;
//        }
//        $childrenWidth -= $this->roundMargin;
//
//        return $childrenWidth > $roundWidth ? $childrenWidth : $roundWidth;
//    }
//
//    private function getRoundWidth(Round $round, int $maxNrOfPoulePlaceColumns): float
//    {
//        $width = $this->pouleMargin;
//        foreach ($round->getPoules() as $poule) {
//            $width += $this->getPouleWidth($poule, $maxNrOfPoulePlaceColumns);
//            $width += $this->pouleMargin;
//        }
//        return $width;
//    }
//
//    public function getPouleWidth(Poule $poule, int $maxNrOfPoulePlaceColumns): int
//    {
//        $nrOfPoulePlaceColumns = count($poule->getPlaces());
//        $nrOfColumns = $nrOfPoulePlaceColumns > $maxNrOfPoulePlaceColumns ? $maxNrOfPoulePlaceColumns : $nrOfPoulePlaceColumns;
//        return $nrOfColumns * $this->placeWidth;
//    }
//
//    private function getPageHeight(Structure $structure, int $maxNrOfPoulePlaceColumns): float
//    {
//        $y = $this->defaultPage->drawHeader('tmp');
//        $headerHeight = -$this->defaultPage->drawSubHeader('tmp', $y);
//        return $headerHeight
//            + $this->getMaxChildrenHeight($structure->getRootRounds(), $maxNrOfPoulePlaceColumns)
//            + $this->pageMargin;
//    }
//
//
//    /**
//     * @param list<Round> $children
//     * @return float
//     */
//    private function getMaxChildrenHeight(array $children, int $maxNrOfPoulePlaceColumns): float
//    {
//        $maxHeight = 0;
//        foreach ($children as $childRound) {
//            $roundHeight = $this->getCumulativeRoundHeight($childRound, $maxNrOfPoulePlaceColumns);
//            if ($roundHeight > $maxHeight) {
//                $maxHeight = $roundHeight;
//            }
//        }
//        return $maxHeight;
//    }
//
//    private function getCumulativeRoundHeight(Round $round, int $maxNrOfPoulePlaceColumns): float
//    {
//        $roundHeight = $this->getRoundHeight($round, $maxNrOfPoulePlaceColumns);
//        $maxChildHeight = $this->getMaxChildrenHeight($round->getChildren(), $maxNrOfPoulePlaceColumns);
//        return $roundHeight + $this->rowHeight + $maxChildHeight;
//    }


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

//    private function getRoundHeight(Round $round, int $maxNrOfPoulePlaceColumns): float
//    {
//        $height = $this->rowHeight; // title
//        $height += $this->rowHeight; // poule title
//        $nrOfPouleRows = $this->getMaxNrOfPoulePlaceRows($round->getPoules(), $maxNrOfPoulePlaceColumns);
//        return $height + ($nrOfPouleRows * $this->rowHeight);
//    }
//
//    /**
//     * @param Collection<int|string, Poule> $poules
//     * @param int $maxNrOfPoulePlaceColumns
//     * @return float
//     */
//    private function getMaxNrOfPoulePlaceRows(Collection $poules, int $maxNrOfPoulePlaceColumns): float
//    {
//        $maxNrOfRows = 0;
//        foreach ($poules as $poule) {
//            $nrOfRows = ceil(count($poule->getPlaces()) / $maxNrOfPoulePlaceColumns);
//            if ($nrOfRows > $maxNrOfRows) {
//                $maxNrOfRows = $nrOfRows;
//            }
//        }
//        return $maxNrOfRows;
//    }
}
