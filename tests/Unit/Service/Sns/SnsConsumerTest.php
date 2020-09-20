<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Unit\Service\Sns;

use Bref\Context\Context;
use Bref\Event\Sns\SnsEvent;
use Bref\Symfony\Messenger\Service\BusDriver;
use Bref\Symfony\Messenger\Service\Sns\SnsConsumer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class SnsConsumerTest extends TestCase
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

        $consumer = new SnsConsumer($busDriver, $bus, $serializer, 'async');
        $event = new SnsEvent([
            'Records' => [
                [

                    'EventSource'=>'aws:sns',
                    'Sns' => [
                        'Message' => $body,
                        'MessageAttributes' => [
                            'Headers' => [
                                'Type'=> 'String',
                                'Value'=> json_encode($headers),
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $consumer->handleSns($event, new Context('', 0, '', ''));
    }
}
