<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs\Structure;

class StructureConfig
{
    private RoundConfig $roundConfig;

    public function __construct(
        private int $padding = 15,
        RoundConfig|null $roundConfig = null,
        PouleConfig|null $pouleConfig = null,
    ) {
        $this->roundConfig = $roundConfig !== null ? $roundConfig : new RoundConfig($pouleConfig);
        if ($padding < 10 || $padding > 30) {
            throw new \Exception('padding should be between 10 and 30');
        }
    }

    public function getPouleConfig(): PouleConfig
    {
        return $this->getRoundConfig()->getPouleConfig();
    }

    public function getRoundConfig(): RoundConfig
    {
        return $this->roundConfig;
    }

    public function getPadding(): int
    {
        return $this->padding;
    }
}
