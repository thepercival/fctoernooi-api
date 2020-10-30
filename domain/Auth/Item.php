<?php

declare(strict_types=1);

namespace FCToernooi\Auth;

class Item
{
    /**
     * @var string
     */
    protected $token;
    /**
     * @var int
     */
    protected $userId;

    public function __construct(string $token, int $userId)
    {
        $this->token = $token;
        $this->userId = $userId;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getUserId()
    {
        return $this->userId;
    }
}
