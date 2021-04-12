<?php

declare(strict_types=1);

namespace FCToernooi\Auth;

class Settings
{
    public function __construct(
        protected string $jwtSecret,
        protected string $jwtAlgorithm,
        protected string $activationSecret
    ) {
    }

    public function getJwtSecret(): string
    {
        return $this->jwtSecret;
    }

    public function getJwtAlgorithm(): string
    {
        return $this->jwtAlgorithm;
    }

    public function getActivationSecret(): string
    {
        return $this->activationSecret;
    }
}
