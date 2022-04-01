<?php

declare(strict_types=1);

namespace App\QueueService;

use App\QueueService;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;

class Pdf extends QueueService
{
    /**
     * @param array<string, mixed> $amqpOptions
     */
    public function __construct(array $amqpOptions)
    {
        parent::__construct($amqpOptions, 'pdf-create');
    }

    public function sendCreatePdf(Pdf\CreateMessage $message, int|null $priority = null): void
    {
        $context = $this->getContext();
        /** @var AmqpTopic $exchange */
        $exchange = $context->createTopic('amq.direct');
        // $topic->setType(AmqpTopic::TYPE_DIRECT);
        $exchange->addFlag(AmqpTopic::FLAG_DURABLE);
        ////$topic->setArguments(['alternate-exchange' => 'foo']);

        $queue = $this->getQueue();
        $context->declareQueue($queue);

        $context->bind(new AmqpBind($exchange, $queue));

        $amqpMessage = $context->createMessage($message->toJson());
        $context->createProducer()->setPriority($priority)->send($queue, $amqpMessage);
    }
}
