<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs\Structure;

final readonly class PouleConfig
{
    private float $fontHeight;

    public function __construct(
        private float $paddingX,
        private float $rowHeight,
        private float $margin,
        float|null $fontHeight = null
    ) {
        if ($rowHeight < 10.0 || $rowHeight > 30.0) {
            throw new \Exception('rowHeight should be between 10 and 30');
        }
        if ($fontHeight === null) {
            $fontHeight = $this->rowHeight - 2.0;
        }

        if ($fontHeight < 10.0 || $fontHeight > 30.0) {
            throw new \Exception('fontHeight should be between 10 and 30');
        }
        $this->fontHeight = $fontHeight;
    }

    public function getPaddingX(): float
    {
        return $this->paddingX;
    }

    public function getMargin(): float
    {
        return $this->margin;
    }

    public function getRowHeight(): float
    {
        return $this->rowHeight;
    }

    public function getFontHeight(): float
    {
        return $this->fontHeight;
    }
}