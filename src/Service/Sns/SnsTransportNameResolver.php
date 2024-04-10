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
        if (!array_key_exists('EventSubscriptionArn', $snsRecord->toArray())) {
            throw new InvalidArgumentException('EventSubscriptionArn is missing in sns record.');
        }

        $eventSourceArn = $snsRecord->getEventSubscriptionArn();

        return $this->configurationProvider->provideTransportFromEventSource(self::TRANSPORT_PROTOCOL . $eventSourceArn);
    }
}
