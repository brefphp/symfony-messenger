<?php declare(strict_types=1);

namespace Bref\Messenger\Service;

use Bref\Messenger\Exception\TypeNotResolvedException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RejectRedeliveredMessageException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Class that consumes messages when SQS/SNS triggers our Lambda with messages.
 *
 * This class will put those messages back onto the Symfony Messenger message bus
 * so that these messages are handled by their handlers.
 */
class BrefWorker
{
    /**
     * @var TypeResolver
     */
    private $typeResolver;

    /**
     * @var Consumer
     */
    private $consumer;

    /**
     *
     * @param TypeResolver $typeResolver
     * @param Consumer $consumer
     */
    public function __construct(TypeResolver $typeResolver, Consumer $consumer)
    {
        $this->typeResolver = $typeResolver;
        $this->consumer = $consumer;
    }

    /**
     * @param mixed $event
     */
    public function consumeLambdaEvent($event): void
    {
        // get type from $event
        $type = $this->typeResolver->getType($event);
        if ($type === null) {
            throw TypeNotResolvedException::create($event);
        }

        $this->consumer->consume($type, $event);
    }

}

