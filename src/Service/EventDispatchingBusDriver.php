<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * A bus driver that dispatches the same worker events as Symfony's
 * `messenger:consume` worker.
 *
 * Symfony builds retries, the failure transport and most monitoring
 * integrations on top of these events, so they only work when something
 * dispatches them. Use this driver instead of the SimpleBusDriver to get
 * that behaviour on Lambda.
 */
final class EventDispatchingBusDriver implements BusDriver
{
    /** @var LoggerInterface */
    private $logger;
    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    public function __construct(LoggerInterface $logger, EventDispatcherInterface $eventDispatcher)
    {
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function putEnvelopeOnBus(MessageBusInterface $bus, Envelope $envelope, string $transportName): ?Envelope
    {
        try {
            $event = new WorkerMessageReceivedEvent($envelope, $transportName);
            $this->eventDispatcher->dispatch($event);
            $envelope = $event->getEnvelope();

            if (! $event->shouldHandle()) {
                return null;
            }

            $envelope = $envelope->with(new ReceivedStamp($transportName), new ConsumedByWorkerStamp);
            $envelope = $bus->dispatch($envelope);

            $this->eventDispatcher->dispatch(new WorkerMessageHandledEvent($envelope, $transportName));

            $message = $envelope->getMessage();
            $this->logger->info('{class} was handled successfully.', [
                'class' => get_class($message),
                'message' => $message,
                'transport' => $transportName,
            ]);

            return $envelope;
        } catch (HandlerFailedException $e) {
            while ($e instanceof HandlerFailedException) {
                $e = $e->getPrevious() ?? $e;
            }

            $this->eventDispatcher->dispatch(new WorkerMessageFailedEvent($envelope, $transportName, $e));

            throw $e;
        }
    }
}
