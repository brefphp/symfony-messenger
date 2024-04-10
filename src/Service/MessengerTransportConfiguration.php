<?php

namespace Bref\Symfony\Messenger\Service;

use InvalidArgumentException;

/** @final */
class MessengerTransportConfiguration
{
    public function __construct(
        private array $messengerTransportsConfiguration
    ) {
    }

    /** @throws InvalidArgumentException */
    public function provideTransportFromEventSource(string $eventSourceWithProtocol): string
    {
        foreach ($this->messengerTransportsConfiguration as $messengerTransport => $messengerOptions) {
            $dsn = $this->extractDsnFromTransport($messengerOptions);

            if ($dsn === $eventSourceWithProtocol) {
                return $messengerTransport;
            }
        }

        throw new InvalidArgumentException(sprintf('No transport found for eventSource "%s".', $eventSourceWithProtocol));
    }

    private function extractDsnFromTransport(string|array $messengerTransport): string
    {
        if (is_array($messengerTransport) && array_key_exists('dsn', $messengerTransport)) {
            return $messengerTransport['dsn'];
        }

        return $messengerTransport;
    }
}
