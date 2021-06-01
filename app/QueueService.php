<?php
declare(strict_types=1);

namespace App;

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Sports\Competition;
use Sports\Queue\PlanningInput\CreatePlanningsEvent;
use SportsPlanning\Input as PlanningInput;

/**
 * sudo rabbitmqctl list_queues
 * sudo rabbitmqctl list_vhosts
 * sudo rabbitmqctl start_app
 *
 * Class QueueService
 * @package App
 */
class QueueService implements CreatePlanningsEvent
{
    private string $queueSuffix;

    /**
     * @param array<string, mixed> $amqpOptions
     */
    public function __construct(private array $amqpOptions)
    {
        if (array_key_exists('suffix', $amqpOptions) === false) {
            throw new \Exception('option queue:suffix is missing', E_ERROR);
        }
        $this->queueSuffix = $amqpOptions['suffix'];
        unset($amqpOptions['suffix']);
    }

    public function sendCreatePlannings(
        PlanningInput $input,
        Competition $competition = null,
        int $startRoundNumber = null
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
        $queue = $this->getContext()->createQueue('process-planning-queue-' . $this->queueSuffix);
        $queue->addFlag(AmqpQueue::FLAG_DURABLE);
        return $queue;
    }
}