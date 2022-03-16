<?php

declare(strict_types=1);

namespace FCToernooi;

use DateTimeImmutable;
use FCToernooi\CreditAction\Name as CreditActionName;
use SportsHelpers\Identifiable;

class CreditAction extends Identifiable
{
    public const MIN_LENGTH_NAME = 1;
    public const MAX_LENGTH_NAME = 6;

    public function __construct(
        protected User $user,
        protected CreditActionName $action,
        protected int $nrOfCredits,
        protected DateTimeImmutable $atDateTime
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCreditActionName(): CreditActionName
    {
        return $this->action;
    }

    public function getNrOfCredits(): int
    {
        return $this->nrOfCredits;
    }

    public function getAtDateTime(): DateTimeImmutable
    {
        return $this->atDateTime;
    }
}
