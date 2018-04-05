<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 29-3-18
 * Time: 13:53
 */

declare(strict_types=1);

namespace FCToernooi;

class Token
{
    protected $decoded;

    public function populate($decoded)
    {
        $this->decoded = $decoded;
    }

    public function hasScope(array $scope)
    {
        return !!count(array_intersect($scope, $this->decoded["scope"]));
    }

    public function isPopulated()
    {
        return $this->decoded !== null;
    }

    public function getUserId()
    {
        return $this->decoded["sub"];
    }
}