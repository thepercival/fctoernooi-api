<?php

declare(strict_types=1);

namespace App\Response;

class ForbiddenResponse extends ErrorResponse
{
    public function __construct(string $message)
    {
        parent::__construct($message, 403);
    }
}
