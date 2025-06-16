<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\Sqs;

use Bref\Event\Sqs\SqsRecord;
use InvalidArgumentException;

/** @final */
class SqsTransportNameResolver
{
    private const TRANSPORT_PROTOCOL = 'sqs://';

    public function __construct(
        private array $messengerTransportsConfiguration
    ) {
    }

    public function __invoke(SqsRecord $sqsRecord): string
    {
        if (! array_key_exists('eventSourceARN', $sqsRecord->toArray())) {
            throw new InvalidArgumentException('EventSourceArn is missing in sqs record.');
        }

        $eventSourceArn = $sqsRecord->toArray()['eventSourceARN'];
        $eventSourceWithProtocol = self::TRANSPORT_PROTOCOL . $eventSourceArn;

        foreach ($this->messengerTransportsConfiguration as $messengerTransport => $messengerOptions) {
            $dsn = $this->extractDsnFromTransport($messengerOptions);

            if ($dsn === $eventSourceWithProtocol) {
                return $messengerTransport;
            }

            // Rebuild SQS ARN from https://sqs.eu-west-3.amazonaws.com/0123456789/messages?key=value
            if (preg_match('/^https:\/\/sqs\.([^.]+)\.amazonaws\.com\/([^\/]+)\/([^?]+)/', (string) $dsn, $matches)) {
                $arn = 'arn:aws:sqs:' . $matches[1] . ':' . $matches[2] . ':' . $matches[3];

                if ($eventSourceWithProtocol === 'sqs://' . $arn) {
                    return $messengerTransport;
                }
            }
        }

        throw new InvalidArgumentException(sprintf('No transport found for eventSource "%s".', $eventSourceWithProtocol));
    }

    private function extractDsnFromTransport(string|array $messengerTransport): string
    {
        if (is_array($messengerTransport) && array_key_exists('dsn', $messengerTransport)) {
            return $messengerTransport['dsn'];
        }

        return $messengerTransport;
    }
}
