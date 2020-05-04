<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\EventBridge;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class EventBridgeTransportFactory implements TransportFactoryInterface
{
    /** @var ContainerInterface */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $source = substr($dsn, strlen('eventbridge://'));

        $eventBridge = $this->container->get('bref.messenger.eventbridge_client');

        return new EventBridgeTransport($eventBridge, $serializer, $source);
    }

    public function supports(string $dsn, array $options): bool
    {
        return strpos($dsn, 'eventbridge://') === 0;
    }
}
