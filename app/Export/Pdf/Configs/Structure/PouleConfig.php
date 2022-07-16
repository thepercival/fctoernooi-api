<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs\Structure;

class PouleConfig
{
    private int $fontHeight;

    public function __construct(
        private int $paddingX,
        private int $rowHeight,
        private int $margin,
        int|null $fontHeight = null
    ) {
        if ($rowHeight < 10 || $rowHeight > 30) {
            throw new \Exception('rowHeight should be between 10 and 30');
        }
        if ($fontHeight === null) {
            $fontHeight = $this->rowHeight - 2;
        }

        if ($fontHeight < 10 || $fontHeight > 30) {
            throw new \Exception('fontHeight should be between 10 and 30');
        }
        $this->fontHeight = $fontHeight;
    }

    public function getPaddingX(): int
    {
        return $this->paddingX;
    }

    public function getMargin(): int
    {
        return $this->margin;
    }

    public function getRowHeight(): int
    {
        return $this->rowHeight;
    }

    public function getFontHeight(): int
    {
        return $this->fontHeight;
    }
}