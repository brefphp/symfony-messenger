<?php
namespace Bref\Symfony\Messenger\Test\Unit\Service;

use Bref\Symfony\Messenger\Service\SimpleBusDriver;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Throwable;

final class SimpleBusDriverTest extends TestCase
{
    use ProphecyTrait;

    private $messengerBus;

    /** @before */
    public function prepare()
    {
        $this->messengerBus = $this->prophesize(MessageBusInterface::class);
    }

    public function test_it_dispatch_message_on_specified_transport_ready_to_be_consumed()
    {
        $sut = new SimpleBusDriver(new NullLogger());
        $envelopeToBeConsumed = new Envelope(new stdClass());
        $this->messengerWillSucceedInConsumingOnTransport($envelopeToBeConsumed, 'my_transport');

        $sut->putEnvelopeOnBus($this->messengerBus->reveal(), $envelopeToBeConsumed, 'my_transport');
    }

    public function test_it_unpack_handler_failed_exception()
    {
        $sut = new SimpleBusDriver(new NullLogger());
        $envelopeToBeConsumed = new Envelope(new stdClass());
        $this->messengerWillFailToConsumeOnTransport(
            $envelopeToBeConsumed,
            'async',
            new UnrecoverableMessageHandlingException('boum')
        );
        $this->expectException(UnrecoverableMessageHandlingException::class);
        $sut->putEnvelopeOnBus($this->messengerBus->reveal(), $envelopeToBeConsumed, 'async');
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
