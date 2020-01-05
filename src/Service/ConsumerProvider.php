<?php

declare(strict_types=1);

namespace Bref\Messenger\Service;

use Bref\Messenger\Exception\ConsumerNotFoundException;
use Psr\Container\ContainerInterface;

/**
 * This class will select the best consumer and forward the $event
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ConsumerProvider implements Consumer
{
    private $locator;

    public function __construct(ContainerInterface $locator)
    {
        $this->locator = $locator;
    }

    public function consume(string $type, array $event): void
    {
        if (!$this->locator->has($type)) {
            throw ConsumerNotFoundException::create($type, $event);
        }

        $this->locator->get($type)->consume($type, $event);
    }

    public static function supportedTypes(): array
    {
        // This consumer is a special case. It should never be tagged with "bref_messenger.consumer"
        return [];
    }


}
