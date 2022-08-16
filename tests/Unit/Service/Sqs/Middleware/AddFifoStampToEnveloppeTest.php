<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Unit\Service\Sqs\Middleware;

use Bref\Symfony\Messenger\Event\Sqs\WithMessageDeduplicationId;
use Bref\Symfony\Messenger\Event\Sqs\WithMessageGroupId;
use Bref\Symfony\Messenger\Service\Sqs\Middleware\AddFifoStampToEnveloppe;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsFifoStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class AddFifoStampToEnveloppeTest extends TestCase
{

    public function testHandleWithGroupIdOnly(): void
    {
        $message = new WithMessageGroupIdMessage('groupId');
        $stack = $this->getMockBuilder(StackInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['next'])
            ->getMock();
        $stack->expects($this->once())
            ->method('next')
            ->willReturn(new EmptyMiddlewareInterface());
        $envelope = new Envelope($message);
        $middleware = new AddFifoStampToEnveloppe();
        $envelope = $middleware->handle($envelope, $stack);
        $stamp = $envelope->last(AmazonSqsFifoStamp::class);
        $this->assertNotNull($stamp);
        /** @var AmazonSqsFifoStamp $stamp */
        $this->assertEquals('groupId', $stamp->getMessageGroupId());
        $this->assertNull($stamp->getMessageDeduplicationId());
    }

    public function testHandleWithDeduplicationIdOnly(): void
    {
        $message = new WithMessageDeduplicationIdMessage('deduplicationId');
        $stack = $this->getMockBuilder(StackInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['next'])
            ->getMock();
        $stack->expects($this->once())
            ->method('next')
            ->willReturn(new EmptyMiddlewareInterface());
        $envelope = new Envelope($message);
        $middleware = new AddFifoStampToEnveloppe();
        $envelope = $middleware->handle($envelope, $stack);
        $stamp = $envelope->last(AmazonSqsFifoStamp::class);
        $this->assertNotNull($stamp);
        /** @var AmazonSqsFifoStamp $stamp */
        $this->assertEquals('deduplicationId', $stamp->getMessageDeduplicationId());
        $this->assertNull($stamp->getMessageGroupId());
    }

    public function testHandleWithGroupIdAndDeduplicationId(): void
    {
        $message = new WithMessageDeduplicationIdAndMessageGroupIdMessage('groupId', 'deduplicationId');
        $stack = $this->getMockBuilder(StackInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['next'])
            ->getMock();
        $stack->expects($this->once())
            ->method('next')
            ->willReturn(new EmptyMiddlewareInterface());
        $envelope = new Envelope($message);
        $middleware = new AddFifoStampToEnveloppe();
        $envelope = $middleware->handle($envelope, $stack);
        $stamp = $envelope->last(AmazonSqsFifoStamp::class);
        $this->assertNotNull($stamp);
        /** @var AmazonSqsFifoStamp $stamp */
        $this->assertEquals('deduplicationId', $stamp->getMessageDeduplicationId());
        $this->assertEquals('groupId', $stamp->getMessageGroupId());
    }

    public function testHandleWithoutId(): void
    {
        $message = new WithoutIdMessage();
        $stack = $this->getMockBuilder(StackInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['next'])
            ->getMock();
        $stack->expects($this->once())
            ->method('next')
            ->willReturn(new EmptyMiddlewareInterface());
        $envelope = new Envelope($message);
        $middleware = new AddFifoStampToEnveloppe();
        $envelope = $middleware->handle($envelope, $stack);
        $stamp = $envelope->last(AmazonSqsFifoStamp::class);
        $this->assertNull($stamp);
    }
}

class EmptyMiddlewareInterface implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        return $envelope;
    }
}


class WithMessageDeduplicationIdAndMessageGroupIdMessage implements WithMessageDeduplicationId, WithMessageGroupId
{
    private string $messageGroupId;
    private string $messageDeduplicationId;

    public function __construct(
        string $messageGroupId,
        string $messageDeduplicationId
    )
    {
        $this->messageGroupId = $messageGroupId;
        $this->messageDeduplicationId = $messageDeduplicationId;
    }

    public function messageDeduplicationId(): string
    {
        return $this->messageDeduplicationId;
    }

    public function messageGroupId(): string
    {
        return $this->messageGroupId;
    }
}


class WithMessageDeduplicationIdMessage implements WithMessageDeduplicationId
{
    private string $messageDeduplicationId;

    public function __construct(
        string $messageDeduplicationId
    )
    {
        $this->messageDeduplicationId = $messageDeduplicationId;
    }

    public function messageDeduplicationId(): string
    {
        return $this->messageDeduplicationId;
    }
}


class WithMessageGroupIdMessage implements WithMessageGroupId
{
    private string $messageGroupId;

    public function __construct(
        string $messageGroupId
    )
    {
        $this->messageGroupId = $messageGroupId;
    }

    public function messageGroupId(): string
    {
        return $this->messageGroupId;
    }
}
class WithoutIdMessage
{
}