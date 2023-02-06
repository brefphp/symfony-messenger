<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Unit\Service\Sqs;

use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use Bref\Symfony\Messenger\Service\BusDriver;
use Bref\Symfony\Messenger\Service\Sqs\SqsConsumer;
use Bref\Symfony\Messenger\Test\Resources\TestMessage\TestMessage;
use LogicException;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsReceivedStamp;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsXrayTraceHeaderStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Throwable;

class SqsConsumerTest extends TestCase
{
    use ProphecyTrait;

    private $busDriver;

    private $serializer;

    private MessageBus $bus;

    /** @before */
    public function prepare()
    {
        $this->busDriver = $this->prophesize(BusDriver::class);
        $this->serializer = $this->prophesize(SerializerInterface::class);
        $this->bus = new MessageBus;
    }

    public function test_batch_events()
    {
        $transport = 'async';
        $sqsRecords = [
            $this->sqsRecordWillSuccessfullyBeHandled(
                new TestMessage('test'),
                'e00c848c-2579-4f6a-a006-ccdc2808ed64',
                'Test message 1',
                $transport,
            ),
            $this->sqsRecordWillSuccessfullyBeHandled(
                new TestMessage('test2'),
                '6c4b71a8-eb2e-4373-9d07-478982ff0905',
                'Test message 2',
                $transport,
            ),
            $this->sqsRecordWillSuccessfullyBeHandled(
                new TestMessage('test3'),
                'f8e71ae8-2ae3-4400-a7a0-1193c1a7210f',
                'Test message 3',
                $transport,
            ),
        ];

        $consumer = new SqsConsumer($this->busDriver->reveal(), $this->bus, $this->serializer->reveal(), $transport);
        $failures = $consumer->handle(['Records' => $sqsRecords], new Context('', 0, '', ''));
        $this->assertEmpty($failures);
    }

    public function test_batch_events_with_failure()
    {
        $transport = 'async';
        $sqsRecords = [
            $this->sqsRecordWillSuccessfullyBeHandled(
                new TestMessage('test'),
                'e00c848c-2579-4f6a-a006-ccdc2808ed64',
                'Test message 1',
                $transport,
            ),
            $this->sqsRecordWillFailDuringHandle(
                new TestMessage('test2'),
                '6c4b71a8-eb2e-4373-9d07-478982ff0905',
                'Test message 2',
                $transport,
            ),
            $this->aSqsRecord(
                'f8e71ae8-2ae3-4400-a7a0-1193c1a7210f',
                'Test message 3',
                ['Special\Header\Name' => 'some data'],
            ),
        ];

        $consumer = new SqsConsumer($this->busDriver->reveal(), $this->bus, $this->serializer->reveal(), $transport);

        $this->expectExceptionMessage('boom');
        $consumer->handle(['Records' => $sqsRecords], new Context('', 0, '', ''));
    }

    public function test_batch_events_failure_with_partial_batch_failure_enabled()
    {
        $transport = 'async';
        $sqsRecords = [
            $this->sqsRecordWillSuccessfullyBeHandled(
                new TestMessage('test'),
                'e00c848c-2579-4f6a-a006-ccdc2808ed64',
                'Test message 1',
                $transport,
            ),
            $this->sqsRecordWillFailDuringHandle(
                new TestMessage('test2'),
                '6c4b71a8-eb2e-4373-9d07-478982ff0905',
                'Test message 2',
                $transport,
            ),
            $this->sqsRecordWillSuccessfullyBeHandled(
                new TestMessage('test3'),
                'f8e71ae8-2ae3-4400-a7a0-1193c1a7210f',
                'Test message 3',
                $transport,
            ),
        ];

        $consumer = new SqsConsumer($this->busDriver->reveal(), $this->bus, $this->serializer->reveal(), $transport, null, true);

        $failures = $consumer->handle(['Records' => $sqsRecords], new Context('', 0, '', ''));

        $this->assertNotContains(['itemIdentifier' => 'e00c848c-2579-4f6a-a006-ccdc2808ed64'], $failures['batchItemFailures']);
        $this->assertContains(['itemIdentifier' => '6c4b71a8-eb2e-4373-9d07-478982ff0905'], $failures['batchItemFailures']);
        $this->assertNotContains(['itemIdentifier' => 'f8e71ae8-2ae3-4400-a7a0-1193c1a7210f'], $failures['batchItemFailures']);
    }

    public function test_batch_events_failure_on_fifo_queue_with_partial_batch_failure_enabled()
    {
        $transport = 'async';
        $sqsRecords = [
            $this->sqsRecordWillSuccessfullyBeHandled(
                new TestMessage('test'),
                'e00c848c-2579-4f6a-a006-ccdc2808ed64',
                'Test message 1',
                $transport,
                true,
            ),
            $this->sqsRecordWillFailDuringHandle(
                new TestMessage('test2'),
                '6c4b71a8-eb2e-4373-9d07-478982ff0905',
                'Test message 2',
                $transport,
                true,
            ),
            $this->aSqsRecord(
                'f8e71ae8-2ae3-4400-a7a0-1193c1a7210f',
                'Test message 3',
                ['Special\Header\Name' => 'some data'],
            ),
        ];

        $consumer = new SqsConsumer($this->busDriver->reveal(), $this->bus, $this->serializer->reveal(), $transport, null, true);

        $failures = $consumer->handle(['Records' => $sqsRecords], new Context('', 0, '', ''));

        $this->assertNotContains(['itemIdentifier' => 'e00c848c-2579-4f6a-a006-ccdc2808ed64'], $failures['batchItemFailures']);
        $this->assertContains(['itemIdentifier' => '6c4b71a8-eb2e-4373-9d07-478982ff0905'], $failures['batchItemFailures']);
        $this->assertContains(['itemIdentifier' => 'f8e71ae8-2ae3-4400-a7a0-1193c1a7210f'], $failures['batchItemFailures']);
    }

    public function test_x_ray_header_is_dispatched_on_bus()
    {
        $xrayTraceId = '709857d6-17c2-11ed-861d-0242ac120002';
        $transport = 'async';
        $sqsRecords = [
            $this->sqsRecordWillSuccessfullyBeHandledWithXRaySupport(
                new TestMessage('test'),
                'e00c848c-2579-4f6a-a006-ccdc2808ed64',
                'Test message 1',
                $transport,
                $xrayTraceId
            ),
            $this->sqsRecordWillSuccessfullyBeHandledWithXRaySupport(
                new TestMessage('test2'),
                '6c4b71a8-eb2e-4373-9d07-478982ff0905',
                'Test message 2',
                $transport,
                $xrayTraceId
            ),
            $this->sqsRecordWillSuccessfullyBeHandledWithXRaySupport(
                new TestMessage('test3'),
                'f8e71ae8-2ae3-4400-a7a0-1193c1a7210f',
                'Test message 3',
                $transport,
                $xrayTraceId
            ),
        ];

        $consumer = new SqsConsumer($this->busDriver->reveal(), $this->bus, $this->serializer->reveal(), $transport);
        $failures = $consumer->handle(['Records' => $sqsRecords], new Context('', 0, '', $xrayTraceId));
        $this->assertEmpty($failures);
    }

    public function test_unrecoverable_exception_during_batch()
    {
        $transport = 'async';
        $sqsRecords = [
            $this->sqsRecordWillSuccessfullyBeHandled(
                new TestMessage('test'),
                'e00c848c-2579-4f6a-a006-ccdc2808ed64',
                'Test message 1',
                $transport,
                true,
            ),
            $this->sqsRecordWillFailDuringHandle(
                new TestMessage('test2'),
                '6c4b71a8-eb2e-4373-9d07-478982ff0905',
                'Test message 2',
                $transport,
                true,
                new UnrecoverableMessageHandlingException('no retry')
            ),
            $this->sqsRecordWillSuccessfullyBeHandled(
                new TestMessage('test3'),
                'f8e71ae8-2ae3-4400-a7a0-1193c1a7210f',
                'Test message 3',
                $transport,
                true,
            ),
        ];

        $consumer = new SqsConsumer($this->busDriver->reveal(), $this->bus, $this->serializer->reveal(), $transport, null, true);

        $failures = $consumer->handle(['Records' => $sqsRecords], new Context('', 0, '', ''));
        $this->assertEmpty($failures);
    }

    private function sqsRecordWillSuccessfullyBeHandled(object $message, string $messageId, string $body, string $transport, bool $fifo = false): array
    {
        return $this->sqsRecordWillSuccessfullyBeHandledWithStamps(
            $message,
            $messageId,
            $body,
            $transport,
            [
                new AmazonSqsReceivedStamp($messageId),
            ],
            $fifo
        );
    }

    private function sqsRecordWillSuccessfullyBeHandledWithXRaySupport(object $message, string $messageId, string $body, string $transport, string $xrayTraceId, bool $fifo = false): array
    {
        return $this->sqsRecordWillSuccessfullyBeHandledWithStamps(
            $message,
            $messageId,
            $body,
            $transport,
            [
                new AmazonSqsReceivedStamp($messageId),
                new AmazonSqsXrayTraceHeaderStamp($xrayTraceId)
            ],
            $fifo
        );
    }

    private function sqsRecordWillSuccessfullyBeHandledWithStamps(object $message, string $messageId, string $body, string $transport, array $stamps = [], bool $fifo = false): array
    {
        $specialHeaders = ['Special\Header\Name' => 'some data'];
        $headers = array_merge($specialHeaders, [
            'Content-Type' => 'application/json',
        ]);

        $this->serializer->decode(['body' => $body, 'headers' => $headers])->willReturn(new Envelope($message));
        $this->busDriver
            ->putEnvelopeOnBus(
                $this->bus,
                new Envelope(
                    $message,
                    $stamps,
                ),
                $transport
            )
            ->shouldBeCalled()
        ;

        return $this->aSqsRecord($messageId, $body, $specialHeaders, $fifo);
    }

    private function sqsRecordWillFailDuringHandle(object $message, string $messageId, string $body, string $transport, bool $fifo = false, ?Throwable $failure = null): array
    {
        $specialHeaders = ['Special\Header\Name' => 'some data'];
        $headers = array_merge($specialHeaders, [
            'Content-Type' => 'application/json',
        ]);

        $this->serializer->decode(['body' => $body, 'headers' => $headers])->willReturn(new Envelope($message));
        $this->busDriver
            ->putEnvelopeOnBus(
                $this->bus,
                new Envelope(
                    $message,
                    [
                        new AmazonSqsReceivedStamp($messageId),
                    ],
                ),
                $transport
            )
            ->willThrow($failure ?? new \Exception('boom'))
        ;

        return $this->aSqsRecord($messageId, $body, $specialHeaders, $fifo);
    }

    private function aSqsRecord(string $messageId, string $body, array $specialHeaders, bool $fifo = false): array
    {
        return [
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
            'eventSourceARN' => 'arn:aws:sqs:us-east-1:123456789012:queue1'.($fifo ? '.fifo' : ''),
        ];
    }
}
