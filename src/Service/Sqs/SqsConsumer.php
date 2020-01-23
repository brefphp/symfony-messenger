<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\Sqs;

use Bref\Symfony\Messenger\Exception\InvalidEvent;
use Bref\Symfony\Messenger\Exception\TypeNotSupported;
use Bref\Symfony\Messenger\Service\BusDriver;
use Bref\Symfony\Messenger\Service\Consumer;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class SqsConsumer implements Consumer
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
     */
    public function consume(string $type, $event): void
    {
        if (! in_array($type, self::supportedTypes())) {
            throw TypeNotSupported::create($type, self::class, $event);
        }

        if (! is_array($event) || ! isset($event['Records'])) {
            throw InvalidEvent::create($type, self::class, $event);
        }

        foreach ($event['Records'] as $record) {
            $envelope = $this->serializer->decode(['body' => $record['body']]);

            $this->busDriver->putEnvelopeOnBus($this->bus, $envelope, $this->transportName);
        }
    }

    public static function supportedTypes(): array
    {
        return ['sqs'];
    }
}
