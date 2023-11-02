<?php

namespace Bref\Symfony\Messenger\Test\Unit\Service\EventBridge;

use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\EventBridge\Result\PutEventsResponse;
use AsyncAws\EventBridge\ValueObject\PutEventsResultEntry;
use Bref\Symfony\Messenger\Service\EventBridge\EventBridgeDetailTypeResolver;
use Bref\Symfony\Messenger\Service\EventBridge\EventBridgeTransport;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class EventBridgeTransportTest extends TestCase
{
    /** @var SerializerInterface|MockObject */
    private $serializer;

    /** @var EventBridgeClient|MockObject */
    private $eventBridge;

    /** @var EventBridgeDetailTypeResolver|MockObject|null */
    private $detailTypeResolver;

    private string $source = 'myapp';

    private ?string $eventBusName = null;

    public function setUp(): void
    {
        $this->eventBusName = null;
        $this->detailTypeResolver = null;
        $this->serializer = $this->getMockForAbstractClass(SerializerInterface::class);
        $this->eventBridge = $this->createMock(EventBridgeClient::class);
    }

    public function testSendSuccess()
    {
        $envelope = new Envelope(new \stdClass());
        $result = $this->createMock(PutEventsResponse::class);

        $this->serializer
            ->expects($this->once())
            ->method('encode')
            ->with($envelope)
            ->willReturn(['body' => 'test']);
        $this->eventBridge
            ->expects($this->once())
            ->method('putEvents')
            ->with(
                [
                    'Entries' => [
                        [
                            'Detail' => '{"body":"test"}',
                            'DetailType' => 'Symfony Messenger message',
                            'Source' => $this->source,
                        ],
                    ],
                ]
            )
            ->willReturn($result);
        $result->expects($this->once())
            ->method('getFailedEntryCount')
            ->willReturn(0);

        $this->assertSame(
            $envelope,
            $this->createTransport()->send($envelope)
        );
    }

    public function testSendSuccessWithCustomBusName()
    {
        $this->eventBusName = 'custom';
        $envelope = new Envelope(new \stdClass());
        $result = $this->createMock(PutEventsResponse::class);

        $this->serializer
            ->expects($this->once())
            ->method('encode')
            ->with($envelope)
            ->willReturn(['body' => 'test']);
        $this->eventBridge
            ->expects($this->once())
            ->method('putEvents')
            ->with(
                [
                    'Entries' => [
                        [
                            'Detail' => '{"body":"test"}',
                            'DetailType' => 'Symfony Messenger message',
                            'Source' => $this->source,
                            'EventBusName' => 'custom',
                        ],
                    ],
                ]
            )
            ->willReturn($result);
        $result->expects($this->once())
            ->method('getFailedEntryCount')
            ->willReturn(0);

        $this->assertSame(
            $envelope,
            $this->createTransport()->send($envelope)
        );
    }

    public function testSendSuccessWithDetailTypeResolver()
    {
        $this->detailTypeResolver = $this->getMockForAbstractClass(EventBridgeDetailTypeResolver::class);
        $envelope = new Envelope(new \stdClass());
        $result = $this->createMock(PutEventsResponse::class);

        $this->serializer
            ->expects($this->once())
            ->method('encode')
            ->with($envelope)
            ->willReturn(['body' => 'test']);
        $this->detailTypeResolver
            ->expects($this->once())
            ->method('resolveDetailType')
            ->with($envelope)
            ->willReturn('stdClass');
        $this->eventBridge
            ->expects($this->once())
            ->method('putEvents')
            ->with(
                [
                    'Entries' => [
                        [
                            'Detail' => '{"body":"test"}',
                            'DetailType' => 'stdClass',
                            'Source' => $this->source,
                        ],
                    ],
                ]
            )
            ->willReturn($result);
        $result->expects($this->once())
            ->method('getFailedEntryCount')
            ->willReturn(0);

        $this->assertSame(
            $envelope,
            $this->createTransport()->send($envelope)
        );
    }

    public function testSendFailed()
    {
        $envelope = new Envelope(new \stdClass());

        $this->serializer
            ->expects($this->once())
            ->method('encode')
            ->with($envelope)
            ->willReturn(['body' => 'test']);
        $this->eventBridge
            ->expects($this->once())
            ->method('putEvents')
            ->willThrowException(new \Exception('event bridge exception'));
        $this->expectException(TransportException::class);

        $this->createTransport()->send($envelope);
    }

    public function testSendResultFailed()
    {
        $envelope = new Envelope(new \stdClass());
        $result = $this->createMock(PutEventsResponse::class);
        $resultErrorEntry = new PutEventsResultEntry(['ErrorMessage' => 'Error message']);

        $this->serializer
            ->expects($this->once())
            ->method('encode')
            ->with($envelope)
            ->willReturn(['body' => 'test']);
        $this->eventBridge
            ->expects($this->once())
            ->method('putEvents')
            ->willReturn($result);
        $result->expects($this->once())
            ->method('getFailedEntryCount')
            ->willReturn(1);
        $result->expects($this->once())
            ->method('getEntries')
            ->willReturn([$resultErrorEntry]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage("1 message(s) could not be published to EventBridge: Error message.");

        $this->assertSame(
            $envelope,
            $this->createTransport()->send($envelope)
        );
    }

    private function createTransport(): EventBridgeTransport
    {
        return new EventBridgeTransport(
            $this->eventBridge,
            $this->serializer,
            $this->source,
            $this->eventBusName,
            $this->detailTypeResolver
        );
    }
}
