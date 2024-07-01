<?php

namespace FCToernooi;

class Theme
{
    public function __construct(
        public readonly string $textColor = '#93c54b',
        public readonly string $bgColor = '#3e3f3a'
    )
    {
    }
}