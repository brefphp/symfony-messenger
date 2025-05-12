<?php

namespace Bref\Symfony\Messenger\Service\Sqs;

use InvalidArgumentException;

final class SQSMessengerTransportConfiguration implements SQSMessengerTransportConfigurationInterface
{
    public function __construct(
        private array $messengerTransportsConfiguration
    ) {
    }

    /** @throws InvalidArgumentException */
    public function provideTransportFromEventSource(string $eventSourceWithProtocol): string
    {
        foreach ($this->messengerTransportsConfiguration as $messengerTransport => $messengerOptions) {
            $dsn = $this->extractDsnFromTransport($messengerOptions);

            if ($dsn === $eventSourceWithProtocol) {
                return $messengerTransport;
            }

            // Rebuild SQS ARN from https://sqs.eu-west-3.amazonaws.com/0123456789/messages?key=value
            if (preg_match('/^https:\/\/sqs\.([^.]+)\.amazonaws\.com\/([^\/]+)\/([^?]+)/', (string) $dsn, $matches)) {
                $arn = 'arn:aws:sqs:'.$matches[1].':'.$matches[2].':'.$matches[3];

                if($eventSourceWithProtocol == 'sqs://' . $arn){
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
