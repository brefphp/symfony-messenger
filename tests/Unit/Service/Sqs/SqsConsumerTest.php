<?php declare(strict_types=1);

namespace Unit\Service\Sqs;

use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use Bref\Symfony\Messenger\Service\BusDriver;
use Bref\Symfony\Messenger\Service\Sqs\SqsConsumer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class SqsConsumerTest extends TestCase
{
    public function testSerializer()
    {
        $busDriver = $this->getMockBuilder(BusDriver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['putEnvelopeOnBus'])
            ->getMock();
        $bus = new MessageBus;
        $serializer = $this->getMockBuilder(SerializerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['encode', 'decode'])
            ->getMock();

        $headers = ['Content-Type' => 'application/json'];
        $body = 'Test message.';

        $serializer->expects($this->once())
            ->method('decode')
            ->with(['body' => $body, 'headers' => $headers])
            ->willReturn(new Envelope(new \stdClass));

        $consumer = new SqsConsumer($busDriver, $bus, $serializer, 'async');
        $event = new SqsEvent([
            'Records' => [
                [
                    'body' => $body,
                    'messageAttributes' => [
                        'Headers' => $headers,
                    ],
                    'eventSource'=>'aws:sqs',
                ],
            ],
        ]);

        $consumer->handleSqs($event, new Context('', 0, '', ''));
    }
}
