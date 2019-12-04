<?php declare(strict_types=1);

namespace Bref\Messenger\Test\TestMessage;

class TestMessage
{
    /** @var string */
    public $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }
}
