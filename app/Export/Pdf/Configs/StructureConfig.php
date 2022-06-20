<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

use App\Export\Pdf\Configs\Structure\PouleConfig;
use App\Export\Pdf\Configs\Structure\RoundConfig;

final class StructureConfig
{
    // protected int $maxPoulesPerLine = 3;

    public function __construct(
        RoundConfig|null $roundConfig = null,
        PouleConfig|null $pouleConfig = null
    )
    {
        if( $roundConfig === null ) {
            $this->roundConfig = $roundConfig !== null ? $roundConfig : new RoundConfig();
        }
        if( $pouleConfig === null ) {
            $this->pouleConfig = $pouleConfig !== null ? $pouleConfig : new PouleConfig();
        }
    }

    public function getRoundConfig(): RoundConfig {
        return $this->roundConfig;
    }

    public function getPouleConfig(): PouleConfig {
        return $this->pouleConfig;
    }
}
