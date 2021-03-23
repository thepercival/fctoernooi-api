<?php

declare(strict_types=1);

namespace FCToernooi\Auth;

class Item
{
    public function __construct(protected string $token, protected int|string $userId)
    {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getUserId(): string|int
    {
        return $this->userId;
    }
}
