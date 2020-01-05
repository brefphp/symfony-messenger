<?php declare(strict_types=1);

namespace Bref\Messenger\Exception;

class TypeNotResolvedException extends \RuntimeException
{
    private $event;

    private function __construct($message)
    {
        parent::__construct($message);
    }

    public static function create($event)
    {
        $e = new self('Could not find type for event.');
        $e->event = $event;

        return $e;
    }
}
