<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\Sqs;

use AsyncAws\Sqs\SqsClient;
use AsyncAws\Sqs\ValueObject\MessageAttributeValue;
use Exception;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;

final class SqsTransport implements TransportInterface
{
    /** @var SerializerInterface */
    private $serializer;
    /** @var SqsClient */
    private $sqs;
    /** @var string */
    private $queueUrl;
    /** @var string|null */
    private $messageGroupId;

    public function __construct(SqsClient $sqs, SerializerInterface $serializer, string $queueUrl, ?string $messageGroupId)
    {
        $this->sqs = $sqs;
        $this->serializer = $serializer;
        $this->queueUrl = $queueUrl;
        $this->messageGroupId = $messageGroupId;
    }

    public function send(Envelope $envelope): Envelope
    {
        /** @var DelayStamp|null $delayStamp */
        $delayStamp = $envelope->last(DelayStamp::class);
        $delay = $delayStamp ? (int) ceil($delayStamp->getDelay()/1000) : 0;

        $encodedMessage = $this->serializer->encode($envelope);

        $headers = $encodedMessage['headers'] ?? [];
        $arguments = [
            'MessageAttributes' => [
                'Headers' => new MessageAttributeValue([
                    'DataType' => 'String',
                    'StringValue' => json_encode($headers, JSON_THROW_ON_ERROR),
                ]),
            ],
            'MessageBody' => $encodedMessage['body'],
            'QueueUrl' => $this->queueUrl,
            'DelaySeconds' => $delay,
        ];

        if ($this->messageGroupId !== null) {
            $arguments['MessageGroupId'] = $this->messageGroupId;
        }

        /** @var TransportMessageIdStamp|null $messageIdStamp */
        $messageIdStamp = $envelope->last(TransportMessageIdStamp::class);
        if ($messageIdStamp !== null) {
            $arguments['MessageDeduplicationId'] = $messageIdStamp->getId();
        }

        try {
            $result = $this->sqs->sendMessage($arguments);
            $messageId = $result->getMessageId();
        } catch (Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if ($messageId === null) {
            throw new TransportException('Could not add a message to the SQS queue');
        }

        return $envelope->with(new TransportMessageIdStamp($messageId));
    }

    public function get(): iterable
    {
        throw new Exception('Not implemented');
    }

    public function ack(Envelope $envelope): void
    {
        throw new Exception('Not implemented');
    }

    public function reject(Envelope $envelope): void
    {
        throw new Exception('Not implemented');
    }
}
