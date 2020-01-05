<?php

declare(strict_types=1);

namespace Bref\Messenger\Service\S3;

use Bref\Messenger\Exception\InvalidEventException;
use Bref\Messenger\Exception\TypeNotSupportedException;
use Bref\Messenger\Message\S3Event;
use Bref\Messenger\Service\AbstractConsumer;
use Symfony\Component\Messenger\Envelope;

class S3Consumer extends AbstractConsumer
{
    public function consume(string $type, $event): void
    {
        if (!in_array($type, self::supportedTypes())) {
            throw TypeNotSupportedException::create($type, self::class, $event);
        }

        if (! is_array($event) || ! isset($event['Records'])) {
            throw InvalidEventException::create($type, self::class, $event);
        }

        foreach ($event['Records'] as $record) {
            $this->doConsume(new Envelope(new S3Event($record)));
        }
    }

    public static function supportedTypes(): array
    {
        return ['s3'];
    }
}