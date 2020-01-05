<?php

declare(strict_types=1);

namespace Bref\Messenger\Exception;

class ConsumerNotFoundException extends \RuntimeException
{
    private $type;
    private $event;

    private function __construct($message)
    {
        parent::__construct($message);
    }

    public static function create(string $type, $event)
    {
        $e = new self(sprintf('Could not find a service to handle event of type "%s".', $type));
        $e->event = $event;
        $e->type = $type;

        return $e;
    }
}