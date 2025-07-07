<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Unit\Service\Sqs;

use Bref\Event\Sqs\SqsRecord;
use Bref\Symfony\Messenger\Service\Sqs\SqsTransportNameResolver;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

final class SqsTransportNameResolverTest extends TestCase
{
    use ProphecyTrait;

    public function test_event_source_can_resolved_as_expected(): void
    {
        $messengerTransportsConfiguration = [
            'async' => [
                'dsn' => 'sqs://arn:aws:sqs:us-east-1:1234567890:some-queue-name',
            ],
        ];

        $transportNameResolver = new SqsTransportNameResolver($messengerTransportsConfiguration);

        $event = new SqsRecord([
            'messageId' => '19dd0b57-b21e-4ac1-bd88-01bbb068cb78',
            'body' => 'Test message.',
            'messageAttributes' => [],
            'attributes' => [
                'ApproximateReceiveCount' => '1',
            ],
            'receiptHandle' => 'AQEBwJnKyrHigUMZj6rYigCgxlaS3SLy0a...',
            'eventSource' => 'aws:sqs',
            'eventSourceARN' => 'arn:aws:sqs:us-east-1:1234567890:some-queue-name',
        ]);

        self::assertSame('async', ($transportNameResolver)($event));
    }

    public function test_event_source_can_resolved_as_expected_with_queue_url(): void
    {
        $messengerTransportsConfiguration = [
            'async' => [
                'dsn' => 'https://sqs.us-east-1.amazonaws.com/1234567890/some-queue-name',
            ],
        ];

        $transportNameResolver = new SqsTransportNameResolver($messengerTransportsConfiguration);

        $event = new SqsRecord([
            'messageId' => '19dd0b57-b21e-4ac1-bd88-01bbb068cb78',
            'body' => 'Test message.',
            'messageAttributes' => [],
            'attributes' => [
                'ApproximateReceiveCount' => '1',
            ],
            'receiptHandle' => 'AQEBwJnKyrHigUMZj6rYigCgxlaS3SLy0a...',
            'eventSource' => 'aws:sqs',
            'eventSourceARN' => 'arn:aws:sqs:us-east-1:1234567890:some-queue-name',
        ]);

        self::assertEquals('async', ($transportNameResolver)($event));
    }

    public function test_throws_exception_if_event_source_arn_does_not_exist(): void
    {
        $messengerTransportsConfiguration = [
            'transport1' => [
                'dsn' => 'sqs://arn:aws:sqs:us-east-1:0123456789:some-queue-name',
            ],
        ];

        $transportNameResolver = new SqsTransportNameResolver($messengerTransportsConfiguration);

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
