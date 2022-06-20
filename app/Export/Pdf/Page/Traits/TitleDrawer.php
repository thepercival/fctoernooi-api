<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page\Traits;

use App\Export\Pdf\Align;
use App\Export\Pdf\Configs\TitleConfig;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;

trait TitleDrawer
{
    public function drawTitle(
        string $title,
        float $y,
        TitleConfig $config = null
    ): float
    {
        if( $config === null ) {
            $config = new TitleConfig();
        }
        $this->setFont($this->helper->getTimesFont(true), $config->getFontHeight());
        $x = self::PAGEMARGIN;
        $displayWidth = $this->getDisplayWidth();
        $rectangle = new Rectangle(
            // new Point($x, $y), new Point($displayWidth, $config->getFontHeight())
            new HorizontalLine(new Point($x, $y), $displayWidth), $config->getFontHeight()
        );
        $this->drawCell($title, $rectangle, Align::Center);
        return $y - (2 * $config->getFontHeight());
    }
}
