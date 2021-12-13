<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Resources\TestMessage;

class TestMessage2
{
    /** @var string */
    public $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }
}
