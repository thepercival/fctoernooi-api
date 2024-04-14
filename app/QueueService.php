<?php

declare(strict_types=1);

namespace App;

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Exception;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpQueue;

/**
 * sudo rabbitmqctl list_queues
 * sudo rabbitmqctl list_vhosts
 * sudo rabbitmqctl start_app
 *
 * Class QueueService
 * @package App
 */
abstract class QueueService
{
    public const MIN_PRIORITY = 0;
    public const MAX_PRIORITY = 9;

    protected string $queueName;
    /**
     * @param array<string, mixed> $amqpOptions
     */
    private array $amqpOptions;

    /**
     * @param array<string, mixed> $amqpOptions
     */
    public function __construct(array $amqpOptions, string $queueName, string|null $suffixField = null)
    {
        if( $suffixField === null ) {
            $suffixField = 'suffix';
        }

        if (array_key_exists($suffixField, $amqpOptions) === false) {
            throw new Exception('option queue:suffix is missing', E_ERROR);
        }
        /** @var string $queueSuffix */
        $queueSuffix = $amqpOptions[$suffixField];
        unset($amqpOptions['suffix']);
        unset($amqpOptions['planningSuffix']);
        $queueSuffix = mb_strlen($queueSuffix) > 0 ? '-' . $queueSuffix : $queueSuffix;

        $this->amqpOptions = $amqpOptions;
        $this->queueName = $queueName . $queueSuffix;
    }

    public function receive(callable $callable, int $timeoutInSeconds): void
    {
        $context = $this->getContext();
        $consumer = $context->createConsumer($this->getQueue());

        $subscriptionConsumer = $context->createSubscriptionConsumer();
        $subscriptionConsumer->subscribe($consumer, $callable);

        $subscriptionConsumer->consume($timeoutInSeconds * 1000);
    }

    protected function getContext(): AmqpContext
    {
        $factory = new AmqpConnectionFactory($this->amqpOptions);
        return $factory->createContext();
    }

    protected function getQueue(): AmqpQueue
    {
        /** @var AmqpQueue $queue */
        $queue = $this->getContext()->createQueue($this->queueName);
        $queue->setArguments(['x-max-priority' => 10]);
        $queue->addFlag(AmqpQueue::FLAG_DURABLE);
        return $queue;
    }
}
