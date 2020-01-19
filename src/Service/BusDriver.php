<?php declare(strict_types=1);

namespace Bref\Messenger\Service;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A small abstraction layer to put a envelope on the bus. It is needed as
 * an extension point. The BusDriver is just putting the messages on the bus.
 */
interface BusDriver
{
    public function putEnvelopeOnBus(MessageBusInterface $bus, Envelope $envelope, string $transportName): void;
}
