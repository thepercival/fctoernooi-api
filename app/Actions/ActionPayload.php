<?php

declare(strict_types=1);

namespace App\Actions;

use JsonSerializable;

class ActionPayload implements JsonSerializable
{
    /**
     * @param int                   $statusCode
     * @param array<string, mixed>|object|null     $data
     * @param ActionError|null      $error
     */
    public function __construct(
        private int $statusCode = 200,
        private array|object|null $data = null,
        private ActionError|null $error = null
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>|object|null
     */
    public function getData(): array|null|object
    {
        return $this->data;
    }

    public function getError(): ActionError|null
    {
        return $this->error;
    }

    /**
     * @return array<string, array<string, mixed>|object|int>
     */
    public function jsonSerialize(): array
    {
        $payload = [
            'statusCode' => $this->statusCode,
        ];

        if ($this->data !== null) {
            $payload['data'] = $this->data;
        } elseif ($this->error !== null) {
            $payload['error'] = $this->error;
        }

        return $payload;
    }
}
