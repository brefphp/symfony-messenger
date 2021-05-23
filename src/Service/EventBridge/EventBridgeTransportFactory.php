<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\EventBridge;

use AsyncAws\EventBridge\EventBridgeClient;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class EventBridgeTransportFactory implements TransportFactoryInterface
{
    /** @var EventBridgeClient */
    private $eventBridge;

    public function __construct(EventBridgeClient $eventBridge)
    {
        $this->eventBridge = $eventBridge;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new EventBridgeTransport($this->eventBridge, $serializer, substr($dsn, strlen('eventbridge://')));
    }

    public function supports(string $dsn, array $options): bool
    {
        return strpos($dsn, 'eventbridge://') === 0;
    }
}
