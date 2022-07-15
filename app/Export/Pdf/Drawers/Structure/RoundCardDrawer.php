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
use App\Export\Pdf\Rectangle;
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
        if ($round->isRoot()) {
            $poulesTop = $top;
        } else {
            $headerBottom = $this->renderRoundCardHeader($page, $round, $top);
            if ($round->getNrOfPlaces() === 2) {
                return $headerBottom;
            }

            $poulesHeight = $headerBottom->getY() - $this->roundDrawer->renderPoules($round, $headerBottom, null);
            $rectangle = new Rectangle($headerBottom, -($poulesHeight + (2 * $this->config->getPadding())));
            $radius = [0, 0, 10, 10];
            $borderColor = $this->getBorderColor($round);
            $page->drawCell('', $rectangle, Align::Center, $borderColor, $radius);

            $poulesTop = $headerBottom->addY(-$this->config->getPadding());
            $startTopWithPadding = $poulesTop->getStart()->addX($this->config->getPadding());
            $widthpWithPadding = $poulesTop->getWidth() - (2 * $this->config->getPadding());
            $poulesTop = new HorizontalLine($startTopWithPadding, $widthpWithPadding);
        }
        $poulesBottomY = $this->roundDrawer->renderPoules($round, $poulesTop, $page);

        $bottomY = $poulesBottomY - $this->config->getPadding();
        $lowestBottomY = $bottomY;
        $childrenTopY = $bottomY - $this->categoryConfig->getPadding();

        $childX = $top->getStart()->getX();
        foreach ($round->getChildren() as $childRound) {
            $childWidth = $this->calculateMinimalCascadingWidth($childRound, $maxNrOfPouleRows);
            $childTop = new HorizontalLine(new Point($childX, $childrenTopY), $childWidth);
            $childBottom = $this->renderRoundCard($page, $childRound, $childTop, $maxNrOfPouleRows);

            if ($childBottom->getY() < $lowestBottomY) {
                $lowestBottomY = $childBottom->getY();
            }
            $childX += $childWidth + $this->categoryConfig->getPadding();
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
            $childrenWidth += $this->config->getPadding();
        }
        $childrenWidth -= $this->config->getPadding();

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
        return max($selfHeight, $childrenMaxHeight) + ($round->isRoot() ? 0 : $this->config->getPadding());
    }

    public function calculateSelfHeight(Round $round, float $width): float
    {
        $top = new HorizontalLine(new Point(0, 0), $width);
        $bottomY = $this->roundDrawer->renderPoules($round, $top, null);
        return $top->getY() - $bottomY;
    }
}
