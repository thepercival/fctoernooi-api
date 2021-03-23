<?php
declare(strict_types=1);

namespace FCToernooi\Auth;

class Token
{
    /**
     * @param array<string, string|int|array>|null $decoded
     */
    public function __construct(protected array|null $decoded = null)
    {
    }

    /**
     * @param array<string, string|int> $scope
     * @return bool
     */
    public function hasScope(array $scope): bool
    {
        $arr = array_intersect($scope, $this->decoded["scope"]);
        return count($arr) > 0;
    }

    public function isPopulated(): bool
    {
        return $this->decoded !== null;
    }

    public function getUserId(): int|string
    {
        /** @var string|int $sub */
        $sub = $this->decoded['sub'];
        return $sub;
    }
}
