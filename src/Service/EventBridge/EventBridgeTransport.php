<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\EventBridge;

use AsyncAws\EventBridge\EventBridgeClient;
use Exception;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;

final class EventBridgeTransport implements TransportInterface
{
    /** @var SerializerInterface */
    private $serializer;
    /** @var EventBridgeClient */
    private $eventBridge;
    /** @var string */
    private $source;
    /** @var string */
    private $eventBusName;

    public function __construct(EventBridgeClient $eventBridge, SerializerInterface $serializer, string $source, ?string $eventBusName = null)
    {
        $this->eventBridge = $eventBridge;
        $this->serializer = $serializer;
        $this->source = $source;
        $this->eventBusName = $eventBusName;
    }

    public function send(Envelope $envelope): Envelope
    {
        $encodedMessage = $this->serializer->encode($envelope);
        $arguments = [
            'Entries' => [
                [
                    'Detail' => json_encode($encodedMessage, JSON_THROW_ON_ERROR),
                    // Ideally here we could put the class name of the message, but how to retrieve it?
                    'DetailType' => 'Symfony Messenger message',
                    'Source' => $this->source,
                ],
            ],
        ];

        if ($this->eventBusName) {
            $arguments['Entries'][0]['EventBusName'] = $this->eventBusName;
        }

        try {
            $result = $this->eventBridge->putEvents($arguments);
            $failedCount = $result->getFailedEntryCount();
        } catch (Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if ($failedCount > 0) {
            foreach ($result->getEntries() as $entry) {
                $reason = $entry->getErrorMessage() ?? 'no reason provided';
                throw new TransportException("$failedCount message(s) could not be published to EventBridge: $reason.");
            }
        }

        return $envelope;
    }

    public function get(): iterable
    {
        throw new Exception('Not implemented');
    }

    public function ack(Envelope $envelope): void
    {
        throw new Exception('Not implemented');
    }

    public function reject(Envelope $envelope): void
    {
        throw new Exception('Not implemented');
    }
}
