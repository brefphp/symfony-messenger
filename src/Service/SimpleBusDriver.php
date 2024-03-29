<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final class SimpleBusDriver implements BusDriver
{
    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function putEnvelopeOnBus(MessageBusInterface $bus, Envelope $envelope, string $transportName): void
    {
        try {
            $envelope = $envelope->with(new ReceivedStamp($transportName), new ConsumedByWorkerStamp);
            $bus->dispatch($envelope);

            $message = $envelope->getMessage();
            $this->logger->info('{class} was handled successfully.', [
                'class' => get_class($message),
                'message' => $message,
                'transport' => $transportName,
            ]);
        } catch (HandlerFailedException $e) {
            while ($e instanceof HandlerFailedException) {
                $e = $e->getPrevious() ?? $e;
            }

            throw $e;
        }
    }
}
