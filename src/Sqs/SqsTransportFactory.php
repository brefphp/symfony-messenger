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
        return new SqsTransport($this->sqs, $this->serializer, $dsn);
    }

    public function supports(string $dsn, array $options): bool
    {
        return preg_match('#^https://sqs\.[\w\-]+\.amazonaws\.com/.+#', $dsn) === 1;
    }
}
