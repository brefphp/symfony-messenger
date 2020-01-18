<?php declare(strict_types=1);

namespace Bref\Messenger\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Using this dispatched will allow use of Symfony's failure strategies.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class SymfonyBusDriver implements BusDriver
{
    private $logger;
    private $eventDispatcher;

    public function __construct(LoggerInterface $logger, EventDispatcherInterface $eventDispatcher)
    {
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function putEnvelopeOnBus(MessageBusInterface $bus, Envelope $envelope, string $transportName): void
    {
        $event = new WorkerMessageReceivedEvent($envelope, $transportName);
        $this->eventDispatcher->dispatch($event);

        if (! $event->shouldHandle()) {
            return;
        }

        try {
            $envelope = $bus->dispatch($envelope->with(new ReceivedStamp($transportName), new ConsumedByWorkerStamp));
        } catch (\Throwable $throwable) {
            if ($throwable instanceof HandlerFailedException) {
                $envelope = $throwable->getEnvelope();
            }

            $this->eventDispatcher->dispatch(new WorkerMessageFailedEvent($envelope, $transportName, $throwable));

            return;
        }

        $this->eventDispatcher->dispatch(new WorkerMessageHandledEvent($envelope, $transportName));

        $message = $envelope->getMessage();
        $context = [
            'message' => $message,
            'transport' => $transportName,
            'class' => \get_class($message),
        ];
        $this->logger->info('{class} was handled successfully (acknowledging to transport).', $context);
    }
}
