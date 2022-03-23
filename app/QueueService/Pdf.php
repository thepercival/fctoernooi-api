<?php

declare(strict_types=1);

namespace App\QueueService;

use App\Export\PdfSubject;
use App\QueueService;
use FCToernooi\Tournament;
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

    public function sendCreatePdf(
        Tournament $tournament,
        PdfSubject $subject,
        int $totalNrOfSubjects,
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

        $content = [
            'tournamentId' => $tournament->getId(),
            'subject' => $subject->value,
            'totalNrOfSubjects' => $totalNrOfSubjects
        ];

        $message = $context->createMessage(json_encode($content));
        $context->createProducer()->setPriority($priority)->send($queue, $message);
    }
}
