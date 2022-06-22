<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

class HeaderConfig
{
    private const DEFAULT_ROWHEIGHT = 18;
    private const DEFAULT_FONTHEIGHT = 14;

    private int $rowHeight;
    private int $fontHeight;

    public function __construct(
        private float|null $yStart,
        int|null $rowHeight = null,
        int|null $fontHeight = null,
    ) {
        if ($rowHeight === null) {
            $rowHeight = self::DEFAULT_ROWHEIGHT;
        }
        $this->rowHeight = $rowHeight;

        if ($fontHeight === null) {
            $fontHeight = self::DEFAULT_FONTHEIGHT;
        }
        $this->fontHeight = $fontHeight;

        if ($fontHeight < 10 || $fontHeight > 20) {
            throw new \Exception('fontHeight should be between 10 and 20');
        }
        if ($rowHeight <= $fontHeight || $rowHeight > 20) {
            throw new \Exception('rowHeight should be between fontheight and 20');
        }
    }

    public function getYStart(): float|null
    {
        return $this->yStart;
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
