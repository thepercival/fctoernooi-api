<?php

declare(strict_types=1);

namespace FCToernooi\Payment;

class IDealIssuer
{
    public function __construct(protected string $id, protected string $name, protected string $imgUrl)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getImgUrl(): string
    {
        return $this->imgUrl;
    }
}
