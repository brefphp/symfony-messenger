<?php declare(strict_types=1);

namespace Bref\Messenger\Sqs;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RejectRedeliveredMessageException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Class that consumes messages when SQS triggers our Lambda with messages.
 *
 * This class will put those messages back onto the Symfony Messenger message bus
 * so that these messages are handled by their handlers.
 */
class SqsConsumer
{
    /** @var MessageBusInterface */
    private $bus;
    /** @var SerializerInterface */
    private $serializer;
    /** @var LoggerInterface */
    private $logger;
    /** @var string */
    private $transportName;
    /**
     * @var EventDispatcherInterface|null
     */
    private $eventDispatcher;

    public function __construct(
        MessageBusInterface $bus,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        string $transportName,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->bus = $bus;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->transportName = $transportName;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param mixed $event
     */
    public function consumeLambdaEvent($event): void
    {
        if (! is_array($event) || ! isset($event['Records'])) {
            throw new RuntimeException('The Lambda event data is not a SQS event');
        }

        foreach ($event['Records'] as $record) {
            $envelope = $this->serializer->decode(['body' => $record['body']]);

            $this->consume($envelope);
        }
    }

    private function consume(Envelope $envelope): void
    {
        $event = new WorkerMessageReceivedEvent($envelope, $this->transportName);
        $this->dispatchEvent($event);

        if (!$event->shouldHandle()) {
            return;
        }

        try {
            $envelope = $this->bus->dispatch($envelope->with(new ReceivedStamp($this->transportName), new ConsumedByWorkerStamp()));
        } catch (\Throwable $throwable) {
            if ($throwable instanceof HandlerFailedException) {
                $envelope = $throwable->getEnvelope();
            }

            $this->dispatchEvent(new WorkerMessageFailedEvent($envelope, $this->transportName, $throwable));

            // This is our way to reject the message.
            return;
        }

        $this->dispatchEvent(new WorkerMessageHandledEvent($envelope, $this->transportName));

        if (null !== $this->logger) {
            $message = $envelope->getMessage();
            $context = [
                'message' => $message,
                'class' => \get_class($message),
            ];
            $this->logger->info('{class} was handled successfully (acknowledging to transport).', $context);
        }
    }

    private function dispatchEvent(object $event): void
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }
}

