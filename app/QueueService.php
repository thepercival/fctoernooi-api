<?php

namespace App;

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\Impl\AmqpBind;
use Voetbal\Competition;
use Interop\Queue\Message;
use Interop\Queue\Consumer;

/**
 * sudo rabbitmqctl list_queues
 * sudo rabbitmqctl list_vhosts
 * sudo rabbitmqctl start_app
 *
 * Class QueueService
 * @package App
 */
class QueueService
{
    public function send(Competition $competition, int $startRoundNumber)
    {
        //            $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
//            $channel = $connection->channel();
        // rabbit@laptop-cdk

        $context = $this->getContext();

        $topic = $context->createTopic('amq.direct');
        // $topic->setType(AmqpTopic::TYPE_DIRECT);
        // $topic->addFlag(AmqpTopic::TYPE_FANOUT);
////$topic->setArguments(['alternate-exchange' => 'foo']);

        $queue = $context->createQueue('process-planning-queue');
        $queue->addFlag(AmqpQueue::FLAG_DURABLE);
        $context->declareQueue($queue);

        $context->bind(new AmqpBind($topic, $queue));

        $content = [
            "competitionId" => $competition->getId(),
            "name" => $competition->getLeague()->getName(),
            "roundNumber" => $startRoundNumber
        ];

        $message = $context->createMessage(json_encode($content));
        $context->createProducer()->send($queue, $message);
    }

    public function receive(callable $callable, int $timeoutInSeconds)
    {
        $context = $this->getContext();
        $queue = $context->createQueue('process-planning-queue');
        $consumer = $context->createConsumer($queue);

        $subscriptionConsumer = $context->createSubscriptionConsumer();
        $subscriptionConsumer->subscribe($consumer, $callable);

        $subscriptionConsumer->consume($timeoutInSeconds * 1000);
    }

    protected function getContext(): AmqpContext
    {
        $factory = new AmqpConnectionFactory(
            [
                'host' => 'localhost',
                'port' => 5672,
                'vhost' => '/',
                'user' => 'guest',
                'pass' => 'guest',
                'persisted' => false,
            ]
        );

        $factory = new AmqpConnectionFactory();
        return $factory->createContext();
    }
}