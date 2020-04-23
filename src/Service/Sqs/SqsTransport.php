<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\Sqs;

use Aws\Sqs\Exception\SqsException;
use Aws\Sqs\SqsClient;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

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
                'Headers' => [
                    'DataType' => 'String',
                    'StringValue' => json_encode($headers, JSON_THROW_ON_ERROR),
                ],
            ],
            'MessageBody' => $encodedMessage['body'],
            'QueueUrl' => $this->queueUrl,
            'DelaySeconds' => $delay,
        ];

        if ($this->messageGroupId !== null) {
            $arguments['MessageGroupId'] = $this->messageGroupId;
        }

        try {
            $result = $this->sqs->sendMessage($arguments);
        } catch (SqsException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if ($result->hasKey('MessageId') === false) {
            throw new TransportException('Could not add a message to the SQS queue');
        }

        return $envelope;
    }

    public function get(): iterable
    {
        try {
            $response = $this->sqs->receiveMessage([
                'QueueUrl' => $this->queueUrl,
                'AttributeNames' => ['ApproximateReceiveCount'],
            ]);
        } catch (SqsException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        foreach ($response['Messages'] ?? [] as $message) {
            try {
                $envelope = $this->serializer->decode([
                    'body' => $message['Body'],
                    'headers' => $message['MessageAttributes']['Headers'] ?? [],
                ]);

                yield $envelope->with(new SqsReceivedStamp($message['ReceiptHandle']));
            } catch (MessageDecodingFailedException $e) {
                $this->sqs->deleteMessage([
                    'QueueUrl' => $this->queueUrl,
                    'ReceiptHandle' => $message['ReceiptHandle'],
                ]);

                throw $e;
            }
        }
    }

    public function ack(Envelope $envelope): void
    {
        try {
            $receiptHandle = $this->findSqsReceivedStamp($envelope)->getReceiptHandle();
            $this->sqs->deleteMessage([
                'QueueUrl' => $this->queueUrl,
                'ReceiptHandle' => $receiptHandle,
            ]);
        } catch (SqsException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function reject(Envelope $envelope): void
    {
        try {
            $receiptHandle = $this->findSqsReceivedStamp($envelope)->getReceiptHandle();
            $this->sqs->deleteMessage([
                'QueueUrl' => $this->queueUrl,
                'ReceiptHandle' => $receiptHandle,
            ]);
        } catch (SqsException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    private function findSqsReceivedStamp(Envelope $envelope): SqsReceivedStamp
    {
        /** @var SqsReceivedStamp|null $sqsReceivedStamp */
        $sqsReceivedStamp = $envelope->last(SqsReceivedStamp::class);

        if ($sqsReceivedStamp === null) {
            throw new LogicException('No SqsReceivedStamp found on the Envelope.');
        }

        return $sqsReceivedStamp;
    }
}
