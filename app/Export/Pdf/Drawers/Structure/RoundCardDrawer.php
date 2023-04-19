<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\Structure;

use App\Export\Pdf\Align;
use App\Export\Pdf\Configs\Structure\CategoryConfig;
use App\Export\Pdf\Configs\Structure\RoundConfig;
use App\Export\Pdf\Drawers\Helper;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page;
use App\Export\Pdf\Point;
use App\Export\Pdf\Poule\RoundWidth;
use App\Export\Pdf\Rectangle;
use Sports\Qualify\Target;
use Sports\Qualify\Target as QualifyTarget;
use Sports\Round;
use Sports\Structure\NameService as StructureNameService;

class RoundCardDrawer
{
    protected Helper $helper;
    protected RoundDrawer $roundDrawer;
    protected RoundConfig $config;

    public function __construct(
        protected StructureNameService $structureNameService,
        protected CategoryConfig $categoryConfig
    ) {
        $this->helper = new Helper();
        $this->config = $this->categoryConfig->getRoundConfig();
        $this->roundDrawer = new RoundDrawer($structureNameService, $this->config);
    }


    // protected int $maxPoulesPerLine = 3;

    public function renderRoundCard(
        Page $page,
        Round $round,
        HorizontalLine $top,
        int $maxNrOfPouleRows
    ): HorizontalLine {
        $pouleMargin = $this->config->getPouleConfig()->getMargin();
        if ($round->isRoot()) {
            $poulesTop = $top;
        } else {
            if( $round->getNumberAsValue() === 2) {
                $rt = 12;
            }
            $headerBottom = $this->renderRoundCardHeader($page, $round, $top);
            if ($round->getNrOfPlaces() === 2) {
                return $headerBottom;
            }
            $poulesHeight = $headerBottom->getY() - $this->roundDrawer->renderPoules($round, $headerBottom, null);
            $rectangle = new Rectangle($headerBottom, -($poulesHeight + (2 * $pouleMargin)));
            $radius = [0, 0, 10, 10];
            $borderColor = $this->getBorderColor($round);
            $page->drawCell('', $rectangle, Align::Center, $borderColor, $radius);

            $poulesTop = $headerBottom->addY(-$pouleMargin);
            $startTopWithMargin = $poulesTop->getStart()->addX($pouleMargin);
            // $widthWithMargin = $poulesTop->getWidth() - (2 * $pouleMargin);
            $poulesTop = new HorizontalLine($startTopWithMargin, $poulesTop->getWidth());
        }
        if( $round->getNumberAsValue() === 3) {
            $rt = 12;
        }
        $poulesBottomY = $this->roundDrawer->renderPoules($round, $poulesTop, $page);

        $bottomY = $poulesBottomY - $pouleMargin;
        $lowestBottomY = $bottomY;
        $childrenTopY = $bottomY - ($round->isRoot() ? 0 : $this->config->getMargin());

        // calculateMinimalCascadingWidth(Round $round, int $maxNrOfPouleRows)
        // bepaal eerst de breedte van alle ronden
        // doe daarna een marginBerekening

        list($leftMarginX, $marginX) = $this->getRoundMarginX(
            $round->getChildren(),
            $maxNrOfPouleRows,
            $top->getWidth()
        );
        $childX = $top->getStart()->getX() + $leftMarginX;

        foreach ($round->getQualifyGroupsLosersReversed() as $qualifyGroup) {
            $childRound = $qualifyGroup->getChildRound();

            $childWidth = $this->calculateMinimalCascadingWidth($childRound, $maxNrOfPouleRows);
            $childTop = new HorizontalLine(new Point($childX, $childrenTopY), $childWidth);
            $childBottom = $this->renderRoundCard($page, $childRound, $childTop, $maxNrOfPouleRows);

            if ($childBottom->getY() < $lowestBottomY) {
                $lowestBottomY = $childBottom->getY();
            }
            $childX += $childWidth + $marginX;
        }
        return new HorizontalLine(new Point($top->getStart()->getX(), $lowestBottomY), $top->getWidth());
    }

    private function getBorderColor(Round $round): string
    {
        $qualifyGroup = $round->getParentQualifyGroup();
        if ($qualifyGroup === null) {
            return 'gray';
        }

        if ($qualifyGroup->getTarget() === QualifyTarget::Winners) {
            switch ($qualifyGroup->getNumber()) {
                case 1:
                    return '#298F00';
                case 2:
                    return '#84CF96';
                case 3:
                    return '#0588BC';
                case 4:
                    return '#00578A';
            }
        }
        if ($qualifyGroup->getTarget() === QualifyTarget::Losers) {
            switch ($qualifyGroup->getNumber()) {
                case 1:
                    return '#FF0000';
                case 2:
                    return '#FF9900';
                case 3:
                    return '#FFCC00';
                case 4:
                    return '#FFFF66';
            }
        }
        return 'gray';
    }

    public function renderRoundCardHeader(Page $page, Round $round, HorizontalLine $horLine): HorizontalLine
    {
        $rectangle = new Rectangle($horLine, -$this->config->getHeaderHeight());
        $roundName = $this->structureNameService->getRoundName($round);
        if ($round->getNrOfPlaces() === 2) {
            // $page->setFont($this->helper->getTimesFont(true), $this->config->getFontHeight());
            $radius = [10, 10, 10, 10];
        } else {
            $radius = [10, 10, 0, 0];
        }
        $borderColor = $this->getBorderColor($round);
        $page->drawCell($roundName, $rectangle, Align::Center, $borderColor, $radius);
        // $page->setFont($this->helper->getTimesFont(), $this->config->getFontHeight());
        return $horLine->addY(-$this->config->getHeaderHeight());
    }


    public function calculateMinimalCascadingWidth(Round $round, int $maxNrOfPouleRows): float
    {
        $selfWidth = $this->calculateMinimalSelfWidth($round, $maxNrOfPouleRows);

        $childrenWidth = 0;
        foreach ($round->getChildren() as $childRound) {
            $childrenWidth += $this->calculateMinimalCascadingWidth($childRound, $maxNrOfPouleRows);
            $childrenWidth += $this->config->getMargin();
        }
        $childrenWidth -= $this->config->getMargin();

        return max($selfWidth, $childrenWidth);
    }


    public function calculateMinimalSelfWidth(Round $round, int $maxNrOfPouleRows): float
    {
        $minimalWidthHeader = $this->calculateHeaderMinimalWidth($round);
        $minimalWidthPoules = $this->roundDrawer->calculateMinimalWidth($round, $maxNrOfPouleRows);
        return max($minimalWidthHeader, $minimalWidthPoules);
    }

    public function calculateHeaderMinimalWidth(Round $round): float
    {
        $name = $this->structureNameService->getRoundName($round);
        $width = $this->helper->getTextWidth(
            ' ' . $name . ' ',
            $this->helper->getTimesFont(),
            $this->config->getFontHeight()
        );

        return $width;
    }

    public function calculateCascadingHeight(Round $round, float $width): float
    {
        $selfHeight = $this->calculateSelfHeight($round, $width);
        $childrenMaxHeight = 0;
        foreach ($round->getChildren() as $childRound) {
            $childHeight = $this->calculateCascadingHeight($childRound, $width);
            if ($childHeight > $childrenMaxHeight) {
                $childrenMaxHeight = $childHeight;
            }
        }
        return ($selfHeight + $childrenMaxHeight) + ($round->isRoot() ? 0 : $this->config->getMargin());
    }

    public function calculateSelfHeight(Round $round, float $width): float
    {
        $top = new HorizontalLine(new Point(0, 0), $width);
        $bottomY = $this->roundDrawer->renderPoules($round, $top, null);

        return $top->getY() - $bottomY;
    }

    /**
     * @param list<Round> $childRounds
     * @param int $maxNrOfPouleRows
     * @param float $totalWidth
     * @return list<float>
     */
    private function getRoundMarginX(array $childRounds, int $maxNrOfPouleRows, float $totalWidth): array
    {
        $roundsWidth = array_map(
            function (Round $childRound) use ($maxNrOfPouleRows): RoundWidth {
                return new RoundWidth(
                    $this->calculateMinimalCascadingWidth($childRound, $maxNrOfPouleRows),
                    $childRound
                );
            },
            $childRounds
        );
        $roundsWidth = array_sum(array_map(fn(RoundWidth $roundWidth) => $roundWidth->getWidth(), $roundsWidth));

        $totalMarginWidth = $totalWidth - $roundsWidth;
        $aroundNrOfMargins = count($childRounds) + 1;
        $aroundMargin = $totalMarginWidth / $aroundNrOfMargins;
        if ($aroundMargin < $this->config->getMargin() && count($childRounds) > 1) {
            $betweenNrOfMargins = count($childRounds) - 1;
            $betweenMargin = $totalMarginWidth / $betweenNrOfMargins;
            return [0, $betweenMargin];
        }

//        if ($round->isRoot()) {
//            if (count($rowPoules) > 1) {
//                $nrOfPaddings = count($rowPoules) + 1;
//                return [0, ($totalWidth - $poulesWidth) / $nrOfPaddings];
//            }
//        }
        return [$aroundMargin, $aroundMargin];
    }
}
