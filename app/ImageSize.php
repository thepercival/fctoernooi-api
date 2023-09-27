<?php

namespace App;

class ImageSize
{
    public function __construct(private string $suffix, private  int $height)
    {
    }

    public function getSuffix(): string {
        return $this->suffix;
    }

    public function getHeight(): int {
        return $this->height;
    }
}