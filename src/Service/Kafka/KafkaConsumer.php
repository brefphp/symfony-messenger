<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\Kafka;

use Bref\Context\Context;
use Bref\Event\Kafka\KafkaEvent;
use Bref\Event\Kafka\KafkaHandler;
use Bref\Symfony\Messenger\Service\BusDriver;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class KafkaConsumer extends KafkaHandler
{
    /** @var BusDriver */
    private $busDriver;

    /** @var MessageBusInterface  */
    private $bus;

    /** @var SerializerInterface */
    private $serializer;

    /** @var string */
    private $transportName;

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

    public function handleKafka(KafkaEvent $event, Context $context): void
    {
        foreach ($event->getRecords() as $record) {
            $envelope = $this->serializer->decode(['body' => $record->getValue(), 'headers' => $record->getHeaders()]);

            $this->busDriver->putEnvelopeOnBus($this->bus, $envelope, $this->transportName);
        }
    }
}
