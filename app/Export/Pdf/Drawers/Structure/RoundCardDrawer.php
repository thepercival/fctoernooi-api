<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\Structure;

use App\Export\Pdf\Align;
use App\Export\Pdf\Configs\Structure\RoundConfig;
use App\Export\Pdf\Drawers\Helper;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use Sports\Round;
use Sports\Structure\NameService as StructureNameService;

class RoundCardDrawer
{
    protected Helper $helper;
    protected RoundDrawer $roundDrawer;

    public function __construct(
        protected StructureNameService $structureNameService,
        protected RoundConfig $config
    ) {
        $this->helper = new Helper();
        $this->roundDrawer = new RoundDrawer($structureNameService, $config);
    }

    // protected int $maxPoulesPerLine = 3;

    public function renderRoundCard(Page $page, Round $round, HorizontalLine $top): HorizontalLine
    {
        $headerBottom = $this->renderRoundCardHeader($page, $round, $top);
        // $cardBodyRectangle = new Rectangle($cardHeaderRectangle->getBottom(), $rectangle->getBottom());

        $poulesHeight = $headerBottom->getY() - $this->roundDrawer->renderPoules($round, $headerBottom, null)->getY();
        $rectangle = new Rectangle($headerBottom, -($poulesHeight + (2 * $this->config->getPadding())));
        $page->drawCell('', $rectangle, Align::Center, 'green');

        $poulesTop = $headerBottom->addY(-$this->config->getPadding());
        $startTopWithPadding = $poulesTop->getStart()->addX($this->config->getPadding());
        $widthpWithPadding = $poulesTop->getWidth() - (2 * $this->config->getPadding());
        $poulesTop = new HorizontalLine($startTopWithPadding, $widthpWithPadding);
        $poulesBottom = $this->roundDrawer->renderPoules($round, $poulesTop, $page);

        return $poulesBottom->addY($this->config->getPadding());
    }

    public function renderRoundCardHeader(Page $page, Round $round, HorizontalLine $horLine): HorizontalLine
    {
        $rectangle = new Rectangle($horLine, -$this->config->getHeaderHeight());
        $roundName = $this->structureNameService->getRoundName($round);
        $page->setFont($this->helper->getTimesFont(true), $this->config->getFontHeight());
        $page->drawCell($roundName, $rectangle, Align::Center, 'blue');
        $page->setFont($this->helper->getTimesFont(), $this->config->getFontHeight());
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
        $bottom = $this->roundDrawer->renderPoules($round, $top, null);
        return $top->getY() - $bottom->getY();
    }
}
