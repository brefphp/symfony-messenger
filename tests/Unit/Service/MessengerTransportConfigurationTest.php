<?php

namespace Bref\Symfony\Messenger\Test\Unit\Service;

use Bref\Symfony\Messenger\Service\MessengerTransportConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MessengerTransportConfigurationTest extends TestCase
{
    public function test_existing_transport_will_be_found_with_existing_event_source_arn(): void
    {
        $messengerTransportConfiguration = new MessengerTransportConfiguration([
            'async_example_one' => 'sqs://arn:aws:sqs:us-east-1:123456789012:example_one',
            'async_example_two' => [
                'dsn' => 'sqs://arn:aws:sqs:us-east-1:123456789012:example_two',
            ],
        ]);

        self::assertSame(
            'async_example_one',
            $messengerTransportConfiguration->provideTransportFromEventSource(
                'sqs://arn:aws:sqs:us-east-1:123456789012:example_one'
            )
        );

        self::assertSame(
            'async_example_two',
            $messengerTransportConfiguration->provideTransportFromEventSource(
                'sqs://arn:aws:sqs:us-east-1:123456789012:example_two'
            )
        );
    }

    public function test_non_existing_transport_for_event_source_arn_will_be_not_found(): void
    {
        $messengerTransportConfiguration = new MessengerTransportConfiguration([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'No transport found for eventSource "sqs://arn:aws:sqs:us-east-1:123456789012:missing".'
        );

        $messengerTransportConfiguration->provideTransportFromEventSource(
            'sqs://arn:aws:sqs:us-east-1:123456789012:missing'
        );
    }
}