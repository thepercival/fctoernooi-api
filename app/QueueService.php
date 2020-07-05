<?php

namespace App;

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\Impl\AmqpBind;
use Voetbal\Competition;
use Voetbal\Planning\Service\Create as CreatePlanningService;
use Voetbal\Planning\Input as PlanningInput;
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
class QueueService implements CreatePlanningService
{
    public function sendCreatePlannings(
        PlanningInput $input,
        Competition $competition = null,
        int $startRoundNumber = null
    ) {
        $context = $this->getContext();

        $topic = $context->createTopic('amq.direct');
        // $topic->setType(AmqpTopic::TYPE_DIRECT);
        // $topic->addFlag(AmqpTopic::TYPE_FANOUT);
////$topic->setArguments(['alternate-exchange' => 'foo']);

        $queue = $context->createQueue('process-planning-queue');
        $queue->addFlag(AmqpQueue::FLAG_DURABLE);
        $context->declareQueue($queue);

        $context->bind(new AmqpBind($topic, $queue));

        $content = ["inputId" => $input->getId()];
        if ($competition !== null) {
            $content["competitionId"] = $competition->getId();
            $content["name"] = $competition->getLeague()->getName();
        }
        if ($startRoundNumber !== null) {
            $content["roundNumber"] = $startRoundNumber;
        }

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
        /*$factory = new AmqpConnectionFactory(
            [
                'host' => 'localhost',
                'port' => 5672,
                'vhost' => '/',
                'user' => 'guest',
                'pass' => 'guest',
                'persisted' => false,
            ]
        );*/
        $factory = new AmqpConnectionFactory();
        return $factory->createContext();
    }
}