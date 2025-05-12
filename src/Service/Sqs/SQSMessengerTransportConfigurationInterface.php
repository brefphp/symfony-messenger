<?php

namespace Bref\Symfony\Messenger\Service\Sqs;

use InvalidArgumentException;

interface SQSMessengerTransportConfigurationInterface
{
    /** @throws InvalidArgumentException */
    public function provideTransportFromEventSource(string $eventSourceWithProtocol): string;
}