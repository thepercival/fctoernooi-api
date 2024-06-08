<?php

namespace App;

class ImageProps
{
    public const string Suffix = '_h_';

    public function __construct(private string $suffix, private ImageSize $imgSize)
    {
    }

    public function getSuffix(): string {
        return $this->suffix;
    }

    public function getHeight(): int {
        return $this->imgSize->value;
    }
}