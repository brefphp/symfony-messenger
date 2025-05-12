<?php

namespace Bref\Symfony\Messenger\Test\Unit\Service\Sqs;

use Bref\Event\Sqs\SqsRecord;
use Bref\Symfony\Messenger\Service\Sqs\SQSMessengerTransportConfigurationInterface;
use Bref\Symfony\Messenger\Service\Sqs\SqsTransportNameResolver;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

final class SqsTransportNameResolverTest extends TestCase
{
    use ProphecyTrait;

    public function test_event_source_can_resolved_as_expected(): void
    {
        $messengerTransportConfiguration = $this->prophesize(SQSMessengerTransportConfigurationInterface::class);
        /** @phpstan-ignore-next-line */
        $messengerTransportConfiguration
            ->provideTransportFromEventSource(Argument::cetera())
            ->willReturn('async');

        $transportNameResolver = new SqsTransportNameResolver($messengerTransportConfiguration->reveal());

        $event = new SqsRecord([
            'messageId' => '19dd0b57-b21e-4ac1-bd88-01bbb068cb78',
            'body' => 'Test message.',
            'messageAttributes' => [],
            'attributes' => [
                'ApproximateReceiveCount' => '1',
            ],
            'receiptHandle' => 'AQEBwJnKyrHigUMZj6rYigCgxlaS3SLy0a...',
            'eventSource' => 'aws:sqs',
            'eventSourceARN' => 'aws:sqs:us-east-1:1234567890:some-queue-name',
        ]);

        self::assertSame('async', ($transportNameResolver)($event));
    }

    public function test_throws_exception_if_event_source_arn_does_not_exist(): void
    {
        $messengerTransportConfiguration = $this->prophesize(SQSMessengerTransportConfigurationInterface::class);
        /** @phpstan-ignore-next-line */
        $messengerTransportConfiguration
            ->provideTransportFromEventSource(Argument::cetera())
            ->willReturn('async');

        $transportNameResolver = new SqsTransportNameResolver($messengerTransportConfiguration->reveal());

        $eventWithMissingeventSourceARN = new SqsRecord([
            'messageId' => '19dd0b57-b21e-4ac1-bd88-01bbb068cb78',
            'body' => 'Test message.',
            'messageAttributes' => [],
            'attributes' => [
                'ApproximateReceiveCount' => '1',
            ],
            'receiptHandle' => 'AQEBwJnKyrHigUMZj6rYigCgxlaS3SLy0a...',
            'eventSource' => 'aws:sqs',
        ]);

        $this->expectException(InvalidArgumentException::class);
        ($transportNameResolver)($eventWithMissingeventSourceARN);
    }
}