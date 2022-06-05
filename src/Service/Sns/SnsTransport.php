<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\Sns;

use AsyncAws\Sns\SnsClient;
use AsyncAws\Sns\ValueObject\MessageAttributeValue;
use Exception;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;

final class SnsTransport implements TransportInterface
{
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
                'Headers' => new MessageAttributeValue(['DataType' => 'String', 'StringValue' => json_encode($headers, JSON_THROW_ON_ERROR)]),
            ],
            'Message' => $encodedMessage['body'],
            'TopicArn' => $this->topic,
        ];

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
