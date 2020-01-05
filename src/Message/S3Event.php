<?php

declare(strict_types=1);

namespace Bref\Messenger\Message;

class S3Event
{
    private $record;

    public function __construct(array $record)
    {
        $this->record = $record;
    }

    public function getRecord(): array
    {
        return $this->record;
    }
}