<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Unit\Service\Sns;

use Bref\Context\Context;
use Bref\Event\Sns\SnsEvent;
use Bref\Symfony\Messenger\Service\BusDriver;
use Bref\Symfony\Messenger\Service\Sns\SnsConsumer;
use Bref\Symfony\Messenger\Service\Sns\SnsTransportNameResolver;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class SnsConsumerTest extends TestCase
{
    private $busDriver;

    private $serializer;

    private MessageBus $bus;

    private $snsTransportNameResolver;

    /** @before */
    public function before(): void
    {
        $this->busDriver = $this->getMockBuilder(BusDriver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['putEnvelopeOnBus'])
            ->getMock();

        $this->bus = new MessageBus;

        $this->serializer = $this->getMockBuilder(SerializerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['encode', 'decode'])
            ->getMock();

        $this->snsTransportNameResolver = $this->getMockBuilder(SnsTransportNameResolver::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function test_serializer()
    {
        $headers = ['Content-Type' => 'application/json'];
        $body = 'Test message.';

        $this->serializer->expects($this->once())
            ->method('decode')
            ->with(['body' => $body, 'headers' => $headers])
            ->willReturn(new Envelope(new \stdClass));

        $this->snsTransportNameResolver->expects($this->once())
            ->method('__invoke')
            ->willReturn('async');

        $consumer = new SnsConsumer(
            $this->busDriver,
            $this->bus,
            $this->serializer,
            null,
            $this->snsTransportNameResolver
        );

        $event = $this->snsEvent($body, $headers);

        $consumer->handleSns($event, new Context('', 0, '', ''));
    }

    public function test_event_with_transport_detection(): void
    {
        $headers = ['Content-Type' => 'application/json'];
        $body = 'Test message.';

        $this->serializer->expects($this->once())
            ->method('decode')
            ->with(['body' => $body, 'headers' => $headers])
            ->willReturn(new Envelope(new \stdClass));

        $this->snsTransportNameResolver->expects($this->once())
            ->method('__invoke')
            ->willReturn('async');

        $consumer = new SnsConsumer(
            $this->busDriver,
            $this->bus,
            $this->serializer,
            null,
            $this->snsTransportNameResolver
        );

        $event = $this->snsEvent($body, $headers);

        $consumer->handleSns($event, new Context('', 0, '', ''));
    }

    public function test_event_with_manually_set_transport(): void
    {
        $headers = ['Content-Type' => 'application/json'];
        $body = 'Test message.';

        $this->serializer->expects($this->once())
            ->method('decode')
            ->with(['body' => $body, 'headers' => $headers])
            ->willReturn(new Envelope(new \stdClass));

        $this->snsTransportNameResolver->expects($this->never())
            ->method('__invoke');

        $consumer = new SnsConsumer(
            $this->busDriver,
            $this->bus,
            $this->serializer,
            'async',
            $this->snsTransportNameResolver
        );

        $event = $this->snsEvent($body, $headers);

        $consumer->handleSns($event, new Context('', 0, '', ''));
    }

    private function snsEvent(string $body, array $headers): SnsEvent
    {
        return new SnsEvent([
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
    }
}
