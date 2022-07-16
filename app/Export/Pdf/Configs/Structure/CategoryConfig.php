<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs\Structure;

class CategoryConfig
{
    public function __construct(
        private int $headerHeight,
        private int $fontHeight,
        private int $margin,
        private RoundConfig $roundConfig

    ) {
        if ($headerHeight < 10 || $headerHeight > 20) {
            throw new \Exception('headerHeight should be between 10 and 20');
        }

        if ($margin < 10 || $margin > 30) {
            throw new \Exception('padding should be between 10 and 30');
        }
        if ($fontHeight < 10 || $fontHeight > 20) {
            throw new \Exception('placeWidth should be between 0 and 100');
        }
    }

    public function getHeaderHeight(): int
    {
        return $this->headerHeight;
    }

    public function getMargin(): int
    {
        return $this->margin;
    }

    public function getFontHeight(): int
    {
        return $this->fontHeight;
    }

    public function getRoundConfig(): RoundConfig
    {
        return $this->roundConfig;
    }
}
