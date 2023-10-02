<?php

declare(strict_types=1);

namespace FCToernooi\Tournament;

use DateTimeImmutable;

class ShellFilter
{
    public function __construct(
        public DateTimeImmutable|null $startDateTime,
        public DateTimeImmutable|null $endDateTime,
        public string|null $name,
        public bool|null $public,
        public bool|null $example
    )
    {

    }
}
