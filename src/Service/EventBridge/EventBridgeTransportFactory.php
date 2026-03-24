<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\EventBridge;

use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\Scheduler\SchedulerClient;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class EventBridgeTransportFactory implements TransportFactoryInterface
{
    /** @var EventBridgeClient */
    private $eventBridge;
    /** @var ?SchedulerClient */
    private $scheduler;

    public function __construct(EventBridgeClient $eventBridge, ?SchedulerClient $scheduler = null)
    {
        $this->eventBridge = $eventBridge;
        $this->scheduler = $scheduler;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given EventBridge DSN "%s" is invalid.', $dsn));
        }

        $query = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
        }

        return new EventBridgeTransport(
            $this->eventBridge,
            $serializer,
            $parsedUrl['host'],
            $query['event_bus_name'] ?? null,
            $this->scheduler,
            $query['schedule_group'] ?? null,
            $query['target_arn'] ?? null,
            $query['role_arn'] ?? null,
        );
    }

    public function supports(string $dsn, array $options): bool
    {
        return strpos($dsn, 'eventbridge://') === 0;
    }
}
