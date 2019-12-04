<?php declare(strict_types=1);

namespace Bref\Messenger\Sqs;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Class that consumes messages when SQS triggers our Lambda with messages.
 *
 * This class will put those messages back onto the Symfony Messenger message bus
 * so that these messages are handled by their handlers.
 */
class SqsConsumer
{
    /** @var MessageBusInterface */
    private $bus;
    /** @var SerializerInterface */
    private $serializer;
    /** @var LoggerInterface */
    private $logger;
    /** @var string */
    private $transportName;

    public function __construct(
        MessageBusInterface $bus,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        string $transportName
    ) {
        $this->bus = $bus;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->transportName = $transportName;
    }

    /**
     * @param mixed $event
     */
    public function consumeLambdaEvent($event): void
    {
        if (! is_array($event) || ! isset($event['Records'])) {
            throw new RuntimeException('The Lambda event data is not a SQS event');
        }

        foreach ($event['Records'] as $record) {
            $envelope = $this->serializer->decode(['body' => $record['body']]);

            $this->consume($envelope);
        }
    }

    private function consume(Envelope $envelope): void
    {
        $this->bus->dispatch($envelope->with(new ReceivedStamp($this->transportName)));

        $message = $envelope->getMessage();
        $this->logger->info('{class} was handled successfully.', [
            'class' => get_class($message),
            'message' => $message,
        ]);
    }
}
