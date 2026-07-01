<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

final readonly class FieldsetListConfig
{
    public function __construct(
        private float $headerFontSize,
        private float $headerTextMargin,
        private float $textFontSize,
        private float $textMargin
    ) {

    }


    public function getHeaderFontSize(): float
    {
        return $this->headerFontSize;
    }

    public function getHeaderTextMargin(): float {
        return $this->headerTextMargin;
    }

    public function getTextFontSize(): float
    {
        return $this->textFontSize;
    }

    public function getTextMargin(): float {
        return $this->textMargin;
    }
}
