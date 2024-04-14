<?php

declare(strict_types=1);

namespace App\QueueService;

use App\QueueService as QueueServiceBase;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use JMS\Serializer\SerializerInterface;
use SportsPlanning\Planning;
use SportsScheduler\Queue\BestPlanningCreatedInterface;

class BestPlanningCreated extends QueueServiceBase
{
    /**
     * @param array<string, mixed> $amqpOptions
     */
    public function __construct(array $amqpOptions)
    {
        parent::__construct($amqpOptions, 'planning-best-created', 'planningSuffix');
    }
}
