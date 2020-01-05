<?php declare(strict_types=1);

namespace Bref\Messenger\Service;

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

abstract class AbstractConsumer implements Consumer
{
    /** @var MessageBusInterface */
    private $bus;
    /** @var SerializerInterface */
    protected $serializer;
    /** @var LoggerInterface */
    protected $logger;
    /** @var string */
    private $transportName;
    /** @var EventDispatcherInterface|null */
    private $eventDispatcher;

    public function __construct(
        LoggerInterface $logger,
        MessageBusInterface $bus,
        SerializerInterface $serializer,
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
     * @param mixed $event from the outside world.
     */
    abstract public function consume(string $type, $event): void;

    final protected function doConsume(Envelope $envelope): void
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

    final protected function dispatchEvent(object $event): void
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }
}

