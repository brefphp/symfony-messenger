<?php declare(strict_types=1);

namespace Bref\Messenger\Service\Sns;

use Bref\Messenger\Exception\InvalidEventException;
use Bref\Messenger\Exception\TypeNotSupportedException;
use Bref\Messenger\Service\AbstractConsumer;

class SnsConsumer extends AbstractConsumer
{
    public function consume(string $type, $event): void
    {
        if (! in_array($type, self::supportedTypes())) {
            throw TypeNotSupportedException::create($type, self::class, $event);
        }

        if (! is_array($event) || ! isset($event['Records'])) {
            throw InvalidEventException::create($type, self::class, $event);
        }

        foreach ($event['Records'] as $record) {
            if (! isset($record['Sns']) || ! isset($record['Sns']['Message'])) {
                throw InvalidEventException::create($type, self::class, $event);
            }

            $envelope = $this->serializer->decode(['body' => $record['Sns']['Message']]);

            $this->doConsume($envelope);
        }
    }

    public static function supportedTypes(): array
    {
        return ['sns'];
    }
}
