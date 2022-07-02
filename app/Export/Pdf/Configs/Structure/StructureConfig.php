<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs\Structure;

class StructureConfig
{
    public function __construct(
        private int $padding,
        private CategoryConfig $categoryConfig
    ) {
        if ($padding < 10 || $padding > 30) {
            throw new \Exception('padding should be between 10 and 30');
        }
    }

    public function getPadding(): int
    {
        return $this->padding;
    }

    public function getCategoryConfig(): CategoryConfig
    {
        return $this->categoryConfig;
    }

    public function getRoundConfig(): RoundConfig
    {
        return $this->getCategoryConfig()->getRoundConfig();
    }

    public function getPouleConfig(): PouleConfig
    {
        return $this->getRoundConfig()->getPouleConfig();
    }


}
