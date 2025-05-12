<?php

namespace Bref\Symfony\Messenger\Test\Unit\Service\Sqs;

use Bref\Symfony\Messenger\Service\Sqs\SQSMessengerTransportConfiguration;
use PHPUnit\Framework\TestCase;

class SqsMessengerTransportConfigurationTest extends TestCase
{

    public function testProviderWithQueueUrl(): void
    {
        $messengerTransportsConfiguration = [
            'transport1' => [
                'dsn' => 'https://sqs.us-east-1.amazonaws.com/0123456789/some-queue-name',
            ]
        ];

        $service = new SQSMessengerTransportConfiguration($messengerTransportsConfiguration);

        $eventSourceWithProtocol = 'sqs://arn:aws:sqs:us-east-1:0123456789:some-queue-name';

        self::assertEquals('transport1', $service->provideTransportFromEventSource($eventSourceWithProtocol));
    }

    public function testProviderWithARN(): void
    {
        $messengerTransportsConfiguration = [
            'transport1' => [
                'dsn' => 'sqs://arn:aws:sqs:us-east-1:0123456789:some-queue-name',
            ]
        ];

        $service = new SQSMessengerTransportConfiguration($messengerTransportsConfiguration);

        $eventSourceWithProtocol = 'sqs://arn:aws:sqs:us-east-1:0123456789:some-queue-name';

        self::assertEquals('transport1', $service->provideTransportFromEventSource($eventSourceWithProtocol));
    }

}