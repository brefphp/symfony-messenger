<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\EventBridge;

use Aws\EventBridge\EventBridgeClient;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class EventBridgeTransportFactory implements TransportFactoryInterface
{
    /** @var EventBridgeClient */
    private $sns;

    public function __construct(EventBridgeClient $sns)
    {
        $this->sns = $sns;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $source = substr($dsn, strlen('eventbridge://'));

        return new EventBridgeTransport($this->sns, $serializer, $source);
    }

    public function supports(string $dsn, array $options): bool
    {
        return strpos($dsn, 'eventbridge://') === 0;
    }
}
