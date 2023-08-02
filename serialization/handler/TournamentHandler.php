<?php

declare(strict_types=1);

namespace FCToernooi\SerializationHandler;

use FCToernooi\Tournament;
use JMS\Serializer\Context;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\JsonDeserializationVisitor;
use Sports\Competition;
use Sports\SerializationHandler\DummyCreator;
use Sports\SerializationHandler\Handler;

class TournamentHandler extends Handler implements SubscribingHandlerInterface
{
    public function __construct(protected DummyCreator $dummyCreator)
    {
    }

    /**
     * @psalm-return list<array<string, int|string>>
     */
    public static function getSubscribingMethods(): array
    {
        return static::getDeserializationMethods(Tournament::class);
    }

    /**
     * @param JsonDeserializationVisitor $visitor
     * @param array{competition: Competition, public: bool, useSelfRegistration: bool} $fieldValue
     * @param array<string, int|string> $type
     * @param Context $context
     * @return Tournament
     */
    public function deserializeFromJson(
        JsonDeserializationVisitor $visitor,
        array $fieldValue,
        array $type,
        Context $context
    ): Tournament {
        $competition = $this->getProperty($visitor, $fieldValue, 'competition', Competition::class);
        $tournament = new Tournament($competition);
        $tournament->setPublic($fieldValue['public']);
        return $tournament;
    }
}
