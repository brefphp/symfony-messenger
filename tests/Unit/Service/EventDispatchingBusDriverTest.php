<?php
namespace Bref\Symfony\Messenger\Test\Unit\Service;

use Bref\Symfony\Messenger\Service\EventDispatchingBusDriver;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Throwable;

final class EventDispatchingBusDriverTest extends TestCase
{
    use ProphecyTrait;

    private $messengerBus;

    /** @var EventDispatcher */
    private $eventDispatcher;

    /** @var list<object> */
    private $dispatchedEvents = [];

    /** @before */
    public function prepare()
    {
        $this->messengerBus = $this->prophesize(MessageBusInterface::class);
        $this->eventDispatcher = new EventDispatcher;
        $this->dispatchedEvents = [];
        foreach ([WorkerMessageReceivedEvent::class, WorkerMessageHandledEvent::class, WorkerMessageFailedEvent::class] as $eventClass) {
            $this->eventDispatcher->addListener($eventClass, function (object $event) {
                $this->dispatchedEvents[] = $event;
            });
        }
    }

    public function test_it_dispatch_message_on_specified_transport_ready_to_be_consumed()
    {
        $sut = new EventDispatchingBusDriver(new NullLogger, $this->eventDispatcher);
        $envelopeToBeConsumed = new Envelope(new stdClass);
        $this->messengerWillSucceedInConsumingOnTransport($envelopeToBeConsumed, 'my_transport');

        $sut->putEnvelopeOnBus($this->messengerBus->reveal(), $envelopeToBeConsumed, 'my_transport');
    }

    public function test_it_unpack_handler_failed_exception()
    {
        $sut = new EventDispatchingBusDriver(new NullLogger, $this->eventDispatcher);
        $envelopeToBeConsumed = new Envelope(new stdClass);
        $this->messengerWillFailToConsumeOnTransport(
            $envelopeToBeConsumed,
            'async',
            new UnrecoverableMessageHandlingException('boum')
        );
        $this->expectException(UnrecoverableMessageHandlingException::class);
        $sut->putEnvelopeOnBus($this->messengerBus->reveal(), $envelopeToBeConsumed, 'async');
    }

    public function test_it_dispatches_received_and_handled_events()
    {
        $sut = new EventDispatchingBusDriver(new NullLogger, $this->eventDispatcher);
        $envelopeToBeConsumed = new Envelope(new stdClass);
        $this->messengerWillSucceedInConsumingOnTransport($envelopeToBeConsumed, 'my_transport');

        $sut->putEnvelopeOnBus($this->messengerBus->reveal(), $envelopeToBeConsumed, 'my_transport');

        $this->assertInstanceOf(WorkerMessageReceivedEvent::class, $this->dispatchedEvents[0]);
        $this->assertSame('my_transport', $this->dispatchedEvents[0]->getReceiverName());
        $this->assertInstanceOf(WorkerMessageHandledEvent::class, $this->dispatchedEvents[1]);
        $this->assertSame('my_transport', $this->dispatchedEvents[1]->getReceiverName());
        $this->assertCount(2, $this->dispatchedEvents);
    }

    public function test_it_dispatches_a_failed_event_when_handling_fails()
    {
        $sut = new EventDispatchingBusDriver(new NullLogger, $this->eventDispatcher);
        $envelopeToBeConsumed = new Envelope(new stdClass);
        $failure = new UnrecoverableMessageHandlingException('boum');
        $this->messengerWillFailToConsumeOnTransport($envelopeToBeConsumed, 'async', $failure);

        try {
            $sut->putEnvelopeOnBus($this->messengerBus->reveal(), $envelopeToBeConsumed, 'async');
            $this->fail('An exception should have been thrown.');
        } catch (UnrecoverableMessageHandlingException $e) {
            // Expected, the driver rethrows after dispatching the event.
        }

        $this->assertInstanceOf(WorkerMessageReceivedEvent::class, $this->dispatchedEvents[0]);
        $this->assertInstanceOf(WorkerMessageFailedEvent::class, $this->dispatchedEvents[1]);
        $this->assertSame($failure, $this->dispatchedEvents[1]->getThrowable());
        $this->assertSame('async', $this->dispatchedEvents[1]->getReceiverName());
    }

    public function test_it_does_not_handle_the_message_when_a_listener_prevents_it()
    {
        $this->eventDispatcher->addListener(WorkerMessageReceivedEvent::class, function (WorkerMessageReceivedEvent $event) {
            $event->shouldHandle(false);
        });
        $sut = new EventDispatchingBusDriver(new NullLogger, $this->eventDispatcher);
        $this->messengerBus->dispatch(null)->shouldNotBeCalled();

        $result = $sut->putEnvelopeOnBus($this->messengerBus->reveal(), new Envelope(new stdClass), 'async');

        $this->assertNull($result);
    }

    private function messengerWillSucceedInConsumingOnTransport(Envelope $envelope, string $transport)
    {
        $this->messengerBus
            ->dispatch($envelope->with(new ReceivedStamp($transport), new ConsumedByWorkerStamp))
            ->willReturn(
                $envelope->with(new HandledStamp('result', 'my.handler'))
            )
            ->shouldBeCalled()
        ;
    }

    private function messengerWillFailToConsumeOnTransport(Envelope $envelope, string $transport, Throwable $failure)
    {
        $this->messengerBus
            ->dispatch($envelope->with(new ReceivedStamp($transport), new ConsumedByWorkerStamp))
            ->willThrow(
                new HandlerFailedException(
                    $envelope->with(new HandledStamp('result', 'my.handler')),
                    [
                        $failure
                    ]
                )
            )
            ->shouldBeCalled()
        ;
    }
}
