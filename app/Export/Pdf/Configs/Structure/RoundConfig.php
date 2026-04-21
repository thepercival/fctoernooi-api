<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs\Structure;

final readonly class RoundConfig
{
    public function __construct(
        private float $headerHeight,
        private float $fontHeight,
        private float $margin,
        private PouleConfig $pouleConfig
    ) {
        if ($headerHeight < 10.0 || $headerHeight > 20.0) {
            throw new \Exception('headerHeight should be between 10 and 20');
        }

        if ($margin < 10.0 || $margin > 30.0) {
            throw new \Exception('padding should be between 10 and 30');
        }
        if ($fontHeight < 10.0 || $fontHeight > 20.0) {
            throw new \Exception('placeWidth should be between 0 and 100');
        }
    }

    public function getHeaderHeight(): float
    {
        return $this->headerHeight;
    }

    public function getMargin(): float
    {
        return $this->margin;
    }

    public function getFontHeight(): float
    {
        return $this->fontHeight;
    }

    public function getPouleConfig(): PouleConfig
    {
        return $this->pouleConfig;
    }
}
