<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\Sns;

use AsyncAws\Sns\SnsClient;
use AsyncAws\Sns\ValueObject\MessageAttributeValue;
use Exception;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;

final class SnsTransport implements TransportInterface
{
    /**
     * Name symfony/amazon-sqs-messenger and SqsConsumer use for the headers they cannot send as
     * individual message attributes.
     */
    private const MESSAGE_ATTRIBUTE_NAME = 'X-Symfony-Messenger';

    /** Name SnsConsumer reads the complete set of headers from. */
    private const HEADERS_ATTRIBUTE_NAME = 'Headers';

    /** @var SerializerInterface */
    private $serializer;
    /** @var SnsClient */
    private $sns;
    /** @var string */
    private $topic;

    public function __construct(SnsClient $sns, SerializerInterface $serializer, string $topic)
    {
        $this->sns = $sns;
        $this->serializer = $serializer;
        $this->topic = $topic;
    }

    public function send(Envelope $envelope): Envelope
    {
        $encodedMessage = $this->serializer->encode($envelope);
        $headers = $encodedMessage['headers'] ?? [];
        $arguments = [
            'MessageAttributes' => [
                self::HEADERS_ATTRIBUTE_NAME => new MessageAttributeValue(['DataType' => 'String', 'StringValue' => json_encode($headers, JSON_THROW_ON_ERROR)]),
            ],
            'Message' => $encodedMessage['body'],
            'TopicArn' => $this->topic,
        ];
        foreach ($this->encodeHeadersForSqsSubscribers($headers) as $name => $value) {
            $arguments['MessageAttributes'][$name] = new MessageAttributeValue([
                'DataType' => 'String',
                'StringValue' => $value,
            ]);
        }
        if (str_contains($this->topic, ".fifo")) {
            $stamps = $envelope->all();
            $dedupeStamp = $stamps[SnsFifoStamp::class][0] ?? false;
            if (!$dedupeStamp || $dedupeStamp instanceof SnsFifoStamp == false) {
                throw new Exception("SnsFifoStamp required for fifo topic");
            }
            $messageGroupId = $dedupeStamp->getMessageGroupId() ?? false;
            $messageDeDuplicationId = $dedupeStamp->getMessageDeduplicationId() ?? false;
            if ($messageDeDuplicationId) {
                $arguments['MessageDeduplicationId'] = $messageDeDuplicationId;
            }
            if ($messageGroupId) {
                $arguments['MessageGroupId'] = $messageGroupId;
            }
        }
        try {
            $result = $this->sns->publish($arguments);
            $messageId = $result->getMessageId();
        } catch (Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if ($messageId === null) {
            throw new TransportException('Could not add a message to the SNS topic');
        }

        return $envelope;
    }

    /**
     * Encodes the headers the way symfony/amazon-sqs-messenger writes them, so that a queue subscribed
     * to this topic can be read by SqsConsumer or by Symfony's own Amazon SQS transport. Both of them
     * ignore the aggregated "Headers" attribute SnsConsumer relies on.
     *
     * @param array<string, mixed> $headers
     *
     * @return array<string, string>
     */
    private function encodeHeadersForSqsSubscribers(array $headers): array
    {
        $attributes = [];
        $specialHeaders = [];

        foreach ($headers as $name => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $value = (string) $value;

            // SNS rejects an empty attribute value, so such a header can only travel inside the aggregate.
            if ($value === '' || ! $this->isValidMessageAttributeName((string) $name)) {
                $specialHeaders[$name] = $value;

                continue;
            }

            $attributes[$name] = $value;
        }

        if ($specialHeaders !== []) {
            $attributes[self::MESSAGE_ATTRIBUTE_NAME] = json_encode($specialHeaders, JSON_THROW_ON_ERROR);
        }

        return $attributes;
    }

    /**
     * Mirrors the rules AWS documents for message attribute names, plus the two names that carry an
     * aggregate and would collide.
     */
    private function isValidMessageAttributeName(string $name): bool
    {
        return $name !== ''
            && $name !== self::MESSAGE_ATTRIBUTE_NAME
            && $name !== self::HEADERS_ATTRIBUTE_NAME
            && strlen($name) <= 256
            && ! str_starts_with($name, '.')
            && ! str_ends_with($name, '.')
            && ! str_starts_with($name, 'AWS.')
            && ! str_starts_with($name, 'Amazon.')
            && ! preg_match('/([^a-zA-Z0-9_\.-]+|\.\.)/', $name);
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
