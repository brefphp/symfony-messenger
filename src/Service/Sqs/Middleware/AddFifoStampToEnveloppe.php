<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\Sqs\Middleware;

use Bref\Symfony\Messenger\Event\Sqs\WithMessageDeduplicationId;
use Bref\Symfony\Messenger\Event\Sqs\WithMessageGroupId;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsFifoStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class AddFifoStampToEnveloppe implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $messageGroupId = null;
        $messageDeduplicationId = null;
        if ($message instanceof WithMessageGroupId) {
            $messageGroupId = $message->messageGroupId();
        }
        if ($message instanceof WithMessageDeduplicationId) {
            $messageDeduplicationId = $message->messageDeduplicationId();
        }

        if ($messageGroupId || $messageDeduplicationId) {
            $envelope = $envelope->with($this->fifoStamp($messageGroupId, $messageDeduplicationId));
        }

        return $stack->next()->handle($envelope, $stack);
    }

    private function fifoStamp(?string $messageGroupId, ?string $messageDeduplicationId): AmazonSqsFifoStamp
    {
        return new AmazonSqsFifoStamp(
            $messageGroupId,
            $messageDeduplicationId,
        );
    }
}
