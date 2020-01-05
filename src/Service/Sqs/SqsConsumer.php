<?php

declare(strict_types=1);

namespace Bref\Messenger\Service\Sqs;

use Bref\Messenger\Exception\TypeNotSupportedException;
use Bref\Messenger\Service\AbstractConsumer;

class SqsConsumer extends AbstractConsumer
{
    public function consume(string $type, $event): void
    {
        if (!in_array($type, self::supportedTypes())) {
            throw TypeNotSupportedException::create($type, self::class, $event);
        }

        if (! is_array($event) || ! isset($event['Records'])) {
            throw new \RuntimeException('The Lambda event data is not a SQS event');
        }

        foreach ($event['Records'] as $record) {
            $envelope = $this->serializer->decode(['body' => $record['body']]);

            $this->doConsume($envelope);
        }
    }

    public static function supportedTypes(): array
    {
        return ['sqs'];
    }
}