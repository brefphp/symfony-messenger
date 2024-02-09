<?php

namespace Bref\Symfony\Messenger\Service\Sqs;

use Bref\Event\Sqs\SqsRecord;
use Bref\Symfony\Messenger\Service\MessengerTransportConfiguration;
use InvalidArgumentException;

/** @final */
class SqsTransportNameResolver
{
    private const TRANSPORT_PROTOCOL = 'sqs://';

    public function __construct(
        private MessengerTransportConfiguration $configurationProvider
    ) {
    }

    public function __invoke(SqsRecord $sqsRecord): string
    {
        if (!array_key_exists('eventSourceARN', $sqsRecord->toArray())) {
            throw new InvalidArgumentException('EventSourceArn is missing in sqs record.');
        }

        $eventSourceArn = $sqsRecord->toArray()['eventSourceARN'];

        return $this->configurationProvider->provideTransportFromEventSource(self::TRANSPORT_PROTOCOL . $eventSourceArn);
    }
}
