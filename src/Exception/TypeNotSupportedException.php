<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Exception;

class TypeNotSupportedException extends \RuntimeException
{
    private $event;
    private $type;

    private function __construct($message)
    {
        parent::__construct($message);
    }

    public static function create(string $type, string $consumer, $event)
    {
        $e = new self(sprintf('Type "%s" is not supported by consumer "%s".', $type, $consumer));
        $e->event = $event;
        $e->type = $type;

        return $e;
    }
}
