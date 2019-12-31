<?php declare(strict_types=1);

namespace Bref\Messenger\Sqs;

use Aws\Sqs\SqsClient;
use Exception;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;

class SqsTransport implements TransportInterface
{
    /** @var SerializerInterface */
    private $serializer;
    /** @var SqsClient */
    private $sqs;
    /** @var string */
    private $queueUrl;
    /** @var string|null */
    private $messageGroupId;

    public function __construct(SqsClient $sqs, ?SerializerInterface $serializer, string $queueUrl, ?string $messageGroupId)
    {
        $this->sqs = $sqs;
        $this->serializer = $serializer ?? new PhpSerializer;
        $this->queueUrl = $queueUrl;
        $this->messageGroupId = $messageGroupId;
    }

    public function send(Envelope $envelope): Envelope
    {
        /** @var DelayStamp|null $delayStamp */
        $delayStamp = $envelope->last(DelayStamp::class);
        $delay = $delayStamp ? ((int) $delayStamp->getDelay()/1000) : 0;

        $encodedMessage = $this->serializer->encode($envelope);

        $headers = $encodedMessage['headers'] ?? [];
        $arguments = [
            'MessageAttributes' => [
                'Headers' => [
                    'DataType' => 'String',
                    'StringValue' => json_encode($headers, JSON_THROW_ON_ERROR),
                ],
            ],
            'MessageBody' => $encodedMessage['body'],
            'QueueUrl' => $this->queueUrl,
            'DelaySeconds' => $delay,
        ];

        if (null !== $this->messageGroupId) {
            $arguments['MessageGroupId'] = $this->messageGroupId;
        }

        try {
            $result = $this->sqs->sendMessage($arguments);
        } catch (Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if ($result->hasKey('MessageId') === false) {
            throw new TransportException('Could not add a message to the SQS queue');
        }

        return $envelope;
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
