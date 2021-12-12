<?php

declare(strict_types=1);

namespace FCToernooi\SerializationHandler;

use JMS\Serializer\Handler\HandlerRegistry;
use Sports\SerializationHandler\DummyCreator;
use Sports\SerializationHandler\Subscriber as SportsSubscriber;

class Subscriber
{
    public function __construct(protected DummyCreator $dummyCreator)
    {
    }

    public function subscribeHandlers(HandlerRegistry $registry): void
    {
        $registry->registerSubscribingHandler(new TournamentHandler($this->dummyCreator));
        (new SportsSubscriber($this->dummyCreator))->subscribeHandlers($registry);
    }
}
