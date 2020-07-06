<?php

namespace App;

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpTopic;
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
    /**
     * @var array
     */
    protected $options;
    /**
     * @var string
     */
    protected $queueSuffix;

    public function __construct(array $options)
    {
        if (array_key_exists("queueSuffix", $options)) {
            $this->queueSuffix = $options["queueSuffix"];
            unset($options["queueSuffix"]);
        }
        $this->options = $options;
    }

    public function sendCreatePlannings(
        PlanningInput $input,
        Competition $competition = null,
        int $startRoundNumber = null
    ) {
        $context = $this->getContext();

        $exchange = $context->createTopic('amq.direct');
        // $topic->setType(AmqpTopic::TYPE_DIRECT);
        $exchange->addFlag(AmqpTopic::FLAG_DURABLE);
////$topic->setArguments(['alternate-exchange' => 'foo']);


        $queue = $this->getQueue();
        $context->declareQueue($queue);

        $context->bind(new AmqpBind($exchange, $queue));

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
        $consumer = $context->createConsumer($this->getQueue());

        $subscriptionConsumer = $context->createSubscriptionConsumer();
        $subscriptionConsumer->subscribe($consumer, $callable);

        $subscriptionConsumer->consume($timeoutInSeconds * 1000);
    }

    protected function getContext(): AmqpContext
    {
        $factory = new AmqpConnectionFactory($this->options);
        return $factory->createContext();
    }

    protected function getQueue(): AmqpQueue
    {
        $queue = $this->getContext()->createQueue('process-planning-queue-' . $this->queueSuffix);
        $queue->addFlag(AmqpQueue::FLAG_DURABLE);
        return $queue;
    }
}