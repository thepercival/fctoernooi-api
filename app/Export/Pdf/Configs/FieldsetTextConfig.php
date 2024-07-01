<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

readonly class FieldsetTextConfig
{
    public function __construct(
        private int $padding,
        private int $headerFontSize,
        private int $headerTextMargin,
        private int $textFontSize,
        private float $textMargin
    ) {

    }

    public function getPadding(): int
    {
        return $this->padding;
    }

    public function getHeaderFontSize(): int
    {
        return $this->headerFontSize;
    }

    public function getHeaderTextMargin(): float {
        return $this->headerTextMargin;
    }

    public function getTextFontSize(): int
    {
        return $this->textFontSize;
    }

    public function getTextMargin(): float {
        return $this->textMargin;
    }
}
