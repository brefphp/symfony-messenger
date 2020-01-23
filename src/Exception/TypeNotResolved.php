<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Exception;

class TypeNotResolved extends \RuntimeException
{
    /** @var mixed */
    private $event;

    /**
     * @param mixed $event
     */
    public static function create($event): self
    {
        $e = new self('Could not find type for event.');
        $e->event = $event;

        return $e;
    }

    /**
     * @return mixed
     */
    public function getEvent()
    {
        return $this->event;
    }
}
