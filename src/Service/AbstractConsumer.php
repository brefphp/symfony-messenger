<?php declare(strict_types=1);

namespace Bref\Messenger\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

abstract class AbstractConsumer implements Consumer
{
    /** @var MessageBusInterface */
    private $bus;
    /** @var SerializerInterface */
    protected $serializer;
    /** @var LoggerInterface */
    protected $logger;
    /** @var string */
    private $transportName;
    /** @var EventDispatcherInterface|null */
    private $eventDispatcher;

    public function __construct(
        LoggerInterface $logger,
        MessageBusInterface $bus,
        SerializerInterface $serializer,
        string $transportName,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->bus = $bus;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->transportName = $transportName;
        $this->eventDispatcher = $eventDispatcher;
    }


    final protected function doConsume(Envelope $envelope): void
    {
        $this->dispatcher->dispatchEnvelope($envelope->with(new ReceivedStamp($this->transportName), new ConsumedByWorkerStamp));
    }
}
