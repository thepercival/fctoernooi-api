<?php

declare(strict_types=1);

namespace App\QueueService;

use App\QueueService;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Sports\Competition;
use Sports\Queue\PlanningInput\CreatePlanningsEvent;
use SportsPlanning\Input as PlanningInput;

class Planning extends QueueService implements CreatePlanningsEvent
{
    /**
     * @param array<string, mixed> $amqpOptions
     */
    public function __construct(array $amqpOptions)
    {
        parent::__construct($amqpOptions, 'planning-create');
    }

    public function sendCreatePlannings(
        PlanningInput $input,
        Competition|null $competition = null,
        int|null $startRoundNumber = null,
        int|null $priority = null
    ): void {
        $context = $this->getContext();
        /** @var AmqpTopic $exchange */
        $exchange = $context->createTopic('amq.direct');
        // $topic->setType(AmqpTopic::TYPE_DIRECT);
        $exchange->addFlag(AmqpTopic::FLAG_DURABLE);
        ////$topic->setArguments(['alternate-exchange' => 'foo']);


        $queue = $this->getQueue();
        $context->declareQueue($queue);

        $context->bind(new AmqpBind($exchange, $queue));

        $content = ['inputId' => $input->getId()];

        if ($competition !== null && $startRoundNumber !== null) {
            $content['competitionId'] = $competition->getId();
            $content['name'] = $competition->getLeague()->getName();
            $content['roundNumber'] = $startRoundNumber;
        }

        $message = $context->createMessage(json_encode($content));
        $context->createProducer()->setPriority($priority)->send($queue, $message);
    }
}
