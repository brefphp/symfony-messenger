<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service;

use Bref\Symfony\Messenger\Exception\TypeNotResolved;

/**
 * Class that consumes messages when SQS/SNS triggers our Lambda with messages.
 *
 * This class will put those messages back onto the Symfony Messenger message bus
 * so that these messages are handled by their handlers.
 */
final class BrefWorker
{
    /** @var TypeResolver */
    private $typeResolver;

    /** @var Consumer */
    private $consumer;

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
            throw TypeNotResolved::create($event);
        }

        $this->consumer->consume($type, $event);
    }
}
