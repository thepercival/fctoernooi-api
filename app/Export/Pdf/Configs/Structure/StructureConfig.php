<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs\Structure;

class StructureConfig
{
    public function __construct(
        private CategoryConfig $categoryConfig
    ) {
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
