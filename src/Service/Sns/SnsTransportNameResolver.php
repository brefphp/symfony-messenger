<?php

namespace Bref\Symfony\Messenger\Service\Sns;

use Bref\Event\Sns\SnsRecord;
use Bref\Symfony\Messenger\Service\MessengerTransportConfiguration;
use InvalidArgumentException;

/** @final */
class SnsTransportNameResolver
{
    private const TRANSPORT_PROTOCOL = 'sns://';

    public function __construct(
        private MessengerTransportConfiguration $configurationProvider
    ) {
    }

    /** @throws InvalidArgumentException */
    public function __invoke(SnsRecord $snsRecord): string
    {
        if (!array_key_exists('TopicArn', $snsRecord->toArray()['Sns'])) {
            throw new InvalidArgumentException('TopicArn is missing in sns record.');
        }

        $eventSourceArn = $snsRecord->getTopicArn();

        return $this->configurationProvider->provideTransportFromEventSource(self::TRANSPORT_PROTOCOL . $eventSourceArn);
    }
}
