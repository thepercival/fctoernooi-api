<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs\Structure;

class RoundConfig
{
    private PouleConfig $pouleConfig;

    public function __construct(
        PouleConfig|null $pouleConfig = null,
        private int $headerHeight = 18,
        private int $fontHeight = 14,
        private int $padding = 15
    ) {
        $this->pouleConfig = $pouleConfig !== null ? $pouleConfig : new PouleConfig();
        if ($headerHeight < 10 || $headerHeight > 20) {
            throw new \Exception('headerHeight should be between 10 and 20');
        }

        if ($padding < 10 || $padding > 30) {
            throw new \Exception('padding should be between 10 and 30');
        }
        if ($fontHeight < 10 || $fontHeight > 20) {
            throw new \Exception('placeWidth should be between 0 and 100');
        }
    }

    public function getPouleConfig(): PouleConfig
    {
        return $this->pouleConfig;
    }

    public function getHeaderHeight(): int
    {
        return $this->headerHeight;
    }

    public function getPadding(): int
    {
        return $this->padding;
    }

    public function getFontHeight(): int
    {
        return $this->fontHeight;
    }
}
