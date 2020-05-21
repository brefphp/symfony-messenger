<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\EventBridge;

use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;
use Bref\Symfony\Messenger\Service\BusDriver;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class EventBridgeConsumer extends EventBridgeHandler
{
    /** @var MessageBusInterface */
    private $bus;
    /** @var SerializerInterface */
    protected $serializer;
    /** @var string */
    private $transportName;
    /** @var BusDriver */
    private $busDriver;

    public function __construct(
        BusDriver $busDriver,
        MessageBusInterface $bus,
        SerializerInterface $serializer,
        string $transportName
    ) {
        $this->busDriver = $busDriver;
        $this->bus = $bus;
        $this->serializer = $serializer;
        $this->transportName = $transportName;
    }

    public function handleEventBridge(EventBridgeEvent $event, Context $context): void
    {
        $envelope = $this->serializer->decode($event->getDetail());
        $this->busDriver->putEnvelopeOnBus($this->bus, $envelope, $this->transportName);
    }
}
