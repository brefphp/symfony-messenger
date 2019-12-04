<?php declare(strict_types=1);

namespace Bref\Messenger\Sqs;

use Aws\Sqs\SqsClient;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class SqsTransportFactory implements TransportFactoryInterface
{
    /** @var SqsClient */
    private $sqs;
    /** @var SerializerInterface|null */
    private $serializer;

    public function __construct(SqsClient $sqs, ?SerializerInterface $serializer = null)
    {
        $this->sqs = $sqs;
        $this->serializer = $serializer;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new SqsTransport($this->sqs, $this->serializer, $this->dsnToQueueUrl($dsn));
    }

    public function supports(string $dsn, array $options): bool
    {
        return strpos($dsn, 'sqs://') === 0;
    }

    /**
     * The Symfony Messenger DSN for the queue must start with `sqs://` so that the bundle can recognize it
     * as a SQS queue URL.
     * However we need to turn it into a real HTTPS URL to be able to use it (else it's not a valid URL).
     */
    private function dsnToQueueUrl(string $dsn): string
    {
        return str_replace('sqs://', 'https://', $dsn);
    }
}
