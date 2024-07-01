<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

readonly class RulesConfig
{
    public function __construct(
        private FieldsetListConfig $fieldsetListConfig
    ) {
    }

    public function getFieldsetListConfig(): FieldsetListConfig
    {
        return $this->fieldsetListConfig;
    }
}
