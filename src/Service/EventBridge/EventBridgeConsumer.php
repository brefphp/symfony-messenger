<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\EventBridge;

use Bref\Context\Context;
use Bref\Event\Handler;
use Bref\Event\InvalidLambdaEvent;
use Bref\Symfony\Messenger\Service\BusDriver;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class EventBridgeConsumer implements Handler
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

    /**
     * @param mixed $event
     * @throws InvalidLambdaEvent
     */
    public function handle($event, Context $context): void
    {
        if (! is_array($event) || ! isset($event['detail'])) {
            throw new InvalidLambdaEvent('EventBridge', $event);
        }

        $envelope = $this->serializer->decode($event['detail']);
        $this->busDriver->putEnvelopeOnBus($this->bus, $envelope, $this->transportName);
    }
}
