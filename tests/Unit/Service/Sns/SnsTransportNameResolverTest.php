<?php

namespace Bref\Symfony\Messenger\Test\Unit\Service\Sns;

use Bref\Event\Sns\SnsEvent;
use Bref\Symfony\Messenger\Service\MessengerTransportConfiguration;
use Bref\Symfony\Messenger\Service\Sns\SnsTransportNameResolver;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

final class SnsTransportNameResolverTest extends TestCase
{
    use ProphecyTrait;

    public function test_event_source_can_resolved_as_expected(): void
    {
        $messengerTransportConfiguration = $this->prophesize(MessengerTransportConfiguration::class);
        /** @phpstan-ignore-next-line */
        $messengerTransportConfiguration
            ->provideTransportFromEventSource(Argument::cetera())
            ->willReturn('async');

        $transportNameResolver = new SnsTransportNameResolver($messengerTransportConfiguration->reveal());

        $event = new SnsEvent([
            'Records' => [
                [
                    'EventSource'=>'aws:sns',
                    'Sns' => [
                        'Message' => 'Test message.',
                        'MessageAttributes' => [
                            'Headers' => [
                                'Type'=> 'String',
                                'Value'=> ['Content-Type' => 'application/json'],
                            ],
                        ],
                        'TopicArn' => 'arn:aws:sns:us-east-1:1234567890:async',
                    ],
                ],
            ],
        ]);

        self::assertSame('async', ($transportNameResolver)($event->getRecords()[0]));
    }

    public function test_throws_exception_if_topic_arn_deos_not_exist(): void
    {
        $messengerTransportConfiguration = $this->prophesize(MessengerTransportConfiguration::class);
        /** @phpstan-ignore-next-line */
        $messengerTransportConfiguration
            ->provideTransportFromEventSource(Argument::cetera())
            ->willReturn('async');

        $transportNameResolver = new SnsTransportNameResolver($messengerTransportConfiguration->reveal());

        $eventWithMissingTopicArn = new SnsEvent([
            'Records' => [
                [
                    'EventSource'=>'aws:sns',
                    'Sns' => [
                        'Message' => 'Test message.',
                        'MessageAttributes' => [
                            'Headers' => [
                                'Type'=> 'String',
                                'Value'=> ['Content-Type' => 'application/json'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        ($transportNameResolver)($eventWithMissingTopicArn->getRecords()[0]);
    }
}