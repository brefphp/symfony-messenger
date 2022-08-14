<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Unit\Service\Sqs;

use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use Bref\Symfony\Messenger\Service\BusDriver;
use Bref\Symfony\Messenger\Service\Sqs\SqsConsumer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsReceivedStamp;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsXrayTraceHeaderStamp;
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

        $specialHeaders = ['Special\Header\Name' => 'some data'];

        $headers = array_merge($specialHeaders, [
            'Content-Type' => 'application/json',
        ]);
        $body = 'Test message.';
        $messageId = 'e00c848c-2579-4f6a-a006-ccdc2808ed64';

        $serializer->expects($this->once())
            ->method('decode')
            ->with(['body' => $body, 'headers' => $headers])
            ->willReturn(new Envelope(new \stdClass));
        $busDriver->expects($this->once())
            ->method('putEnvelopeOnBus')
            ->with($bus, new Envelope(
                new \stdClass(),
                [
                    new AmazonSqsReceivedStamp($messageId),
                ]), 'async');
        $consumer = new SqsConsumer($busDriver, $bus, $serializer, 'async');
        $event = new SqsEvent([
            'Records' => [
                [
                    'body' => $body,
                    'messageAttributes' => [
                        'Content-Type' => [
                            'dataType' => 'String',
                            'stringValue' => 'application/json',
                        ],
                        'X-Symfony-Messenger' => [
                            'dataType' => 'String',
                            'stringValue' => json_encode($specialHeaders),
                        ],
                    ],
                    'eventSource'=>'aws:sqs',
                    'messageId' => $messageId,
                    'eventSourceARN' => 'arn:aws:sqs:us-east-1:123456789012:queue1'
                ],
            ],
        ]);

        $consumer->handleSqs($event, new Context('', 0, '', ''));
    }

    public function testSerializerWithXRayHeader()
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

        $specialHeaders = ['Special\Header\Name' => 'some data'];

        $headers = array_merge($specialHeaders, [
            'Content-Type' => 'application/json',
        ]);
        $body = 'Test message.';
        $xrayTraceId = '709857d6-17c2-11ed-861d-0242ac120002';
        $messageId = 'e00c848c-2579-4f6a-a006-ccdc2808ed64';
        $serializer->expects($this->once())
            ->method('decode')
            ->with(['body' => $body, 'headers' => $headers])
            ->willReturn(new Envelope(new \stdClass));
        $busDriver->expects($this->once())
            ->method('putEnvelopeOnBus')
            ->with($bus, new Envelope(
                new \stdClass(),
                [
                    new AmazonSqsReceivedStamp($messageId),
                    new AmazonSqsXrayTraceHeaderStamp($xrayTraceId)
                ]), 'async');
        $consumer = new SqsConsumer($busDriver, $bus, $serializer, 'async');
        $event = new SqsEvent([
            'Records' => [
                [
                    'body' => $body,
                    'messageAttributes' => [
                        'Content-Type' => [
                            'dataType' => 'String',
                            'stringValue' => 'application/json',
                        ],
                        'X-Symfony-Messenger' => [
                            'dataType' => 'String',
                            'stringValue' => json_encode($specialHeaders),
                        ],
                    ],
                    'eventSource'=>'aws:sqs',
                    'messageId' => $messageId,
                    'eventSourceARN' => 'arn:aws:sqs:us-east-1:123456789012:queue1'
                ],
            ],
        ]);

        $consumer->handleSqs($event, new Context('', 0, '', $xrayTraceId));
    }
}
