<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Exception;

class ConsumerNotFound extends \RuntimeException
{
    /** @var mixed */
    private $event;

    /** @var string */
    private $type;

    /**
     * @param mixed $event
     */
    public static function create(string $type, $event): self
    {
        $e = new self(sprintf('Could not find a service to handle event of type "%s".', $type));
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
