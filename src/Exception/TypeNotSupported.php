<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Exception;

class TypeNotSupported extends \RuntimeException
{
    /** @var mixed */
    private $event;

    /** @var string */
    private $type;

    /**
     * @param mixed $event
     */
    public static function create(string $type, string $consumer, $event): self
    {
        $e = new self(sprintf('Type "%s" is not supported by consumer "%s".', $type, $consumer));
        $e->event = $event;
        $e->type = $type;

        return $e;
    }

    /**
     * @return mixed
     */
    public function getEvent()
    {
        return $this->event;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
