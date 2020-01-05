<?php

declare(strict_types=1);

namespace Bref\Messenger\Exception;

class InvalidEventException extends \RuntimeException
{
    private $event;
    private $type;

    private function __construct($message)
    {
        parent::__construct($message);
    }

    public static function create(string $type, string $consumer, $event)
    {
        $e = new self(sprintf('This Lambda event data is not an event supported by "%s"', $consumer));
        $e->event = $event;
        $e->type = $type;

        return $e;
    }
}