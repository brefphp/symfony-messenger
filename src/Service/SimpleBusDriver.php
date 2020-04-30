<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;


final class SimpleBusDriver implements BusDriver
{
    /** @var LoggerInterface */
    private $logger;
    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    public function __construct(LoggerInterface $logger, EventDispatcherInterface $eventDispatcher = null)
    {
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function putEnvelopeOnBus(MessageBusInterface $bus, Envelope $envelope, string $transportName): void
    {
        $event = new WorkerMessageReceivedEvent($envelope, $transportName);
        $this->dispatchEvent($event);

        if (!$event->shouldHandle()) {
            return;
        }

        try {
            $bus->dispatch($envelope->with(new ReceivedStamp($transportName), new ConsumedByWorkerStamp));
        } catch (\Throwable $throwable) {
            if ($throwable instanceof HandlerFailedException) {
                $envelope = $throwable->getEnvelope();
            }

            $this->dispatchEvent(new WorkerMessageFailedEvent($envelope, $transportName, $throwable));

            throw $throwable;
        }

        $this->dispatchEvent(new WorkerMessageHandledEvent($envelope, $transportName));

        $message = $envelope->getMessage();
        $this->logger->info('{class} was handled successfully.', [
            'class' => get_class($message),
            'message' => $message,
            'transport' => $transportName,
        ]);
    }

    private function dispatchEvent(object $event): void
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }
}
