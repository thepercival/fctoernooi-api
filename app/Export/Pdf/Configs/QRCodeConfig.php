<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

readonly class QRCodeConfig
{
    public function __construct(
        private int $fontHeight = 14/*; between 10 and 20*/,
    ) {
        if ($fontHeight < 10 || $fontHeight > 20) {
            throw new \Exception('title-fontHeight should be between 10 and 20');
        }
    }

    public function getFontHeight(): int
    {
        return $this->fontHeight;
    }
}
