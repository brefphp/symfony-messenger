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
    private SerializerInterface $serializer;

    private EventBridgeClient $eventBridge;

    private string $source;

    private ?string $eventBusName;

    private ?EventBridgeDetailTypeResolver $detailTypeResolver;

    public function __construct(
        EventBridgeClient $eventBridge,
        SerializerInterface $serializer,
        string $source,
        ?string $eventBusName = null,
        ?EventBridgeDetailTypeResolver $detailTypeResolver = null
    ) {
        $this->eventBridge = $eventBridge;
        $this->serializer = $serializer;
        $this->source = $source;
        $this->eventBusName = $eventBusName;
        $this->detailTypeResolver = $detailTypeResolver;
    }

    public function send(Envelope $envelope): Envelope
    {
        $encodedMessage = $this->serializer->encode($envelope);
        $arguments = [
            'Entries' => [
                [
                    'Detail' => json_encode($encodedMessage, JSON_THROW_ON_ERROR),
                    'DetailType' => $this->detailTypeResolver !== null ?
                        $this->detailTypeResolver->resolveDetailType($envelope) :
                        'Symfony Messenger message',
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
                throw new TransportException(Symfony Messenger message);
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
