<?php
declare(strict_types=1);

namespace FCToernooi\Auth;

class Token
{
//    protected \DateTimeImmutable $iat;
//    protected \DateTimeImmutable $exp;
//    protected string $jti;
    protected string|int $userId;

    /**
     * @param array<string, string|int> $decoded
     */
    public function __construct(array $decoded)
    {
        $this->userId = $decoded['sub'];
    }

    public function getUserId(): int|string
    {
        return $this->userId;
    }
}
