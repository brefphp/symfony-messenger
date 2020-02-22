<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\Sqs;

use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use Bref\Event\Sqs\SqsHandler;
use Bref\Symfony\Messenger\Service\BusDriver;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class SqsConsumer extends SqsHandler
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

    public function handleSqs(SqsEvent $event, Context $context): void
    {
        foreach ($event->getRecords() as $record) {
            $envelope = $this->serializer->decode(['body' => $record->getBody()]);

            $this->busDriver->putEnvelopeOnBus($this->bus, $envelope, $this->transportName);
        }
    }
}
