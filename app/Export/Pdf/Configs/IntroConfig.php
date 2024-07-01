<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

readonly class IntroConfig
{
    public function __construct(
        private FieldsetTextConfig $introFieldsetTextConfig,
        private FieldsetListConfig $rulesFieldsetListConfig
    ) {

    }

    public function getIntroFieldsetTextConfig(): FieldsetTextConfig
    {
        return $this->introFieldsetTextConfig;
    }

    public function getRulesFieldsetListConfig(): FieldsetListConfig
    {
        return $this->rulesFieldsetListConfig;
    }
}
