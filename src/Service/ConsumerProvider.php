<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service;

use Bref\Symfony\Messenger\Exception\ConsumerNotFound;

/**
 * This class will select the best consumer and forward the $event
 */
class ConsumerProvider implements Consumer
{
    /** @var Consumer[] */
    private $consumers;

    public function __construct(iterable $consumers)
    {
        $this->consumers = $consumers;
    }

    public function consume(string $type, array $event): void
    {
        if (! isset($this->consumers[$type])) {
            throw ConsumerNotFound::create($type, $event);
        }

        $this->consumers[$type]->consume($type, $event);
    }

    public static function supportedTypes(): array
    {
        // This consumer is a special case. It should never be tagged with "bref_messenger.consumer"
        return [];
    }
}
