<?php declare(strict_types=1);

namespace Bref\Messenger\Service\Sqs;

use Aws\Sqs\SqsClient;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class SqsTransportFactory implements TransportFactoryInterface
{
    /** @var SqsClient */
    private $sqs;

    public function __construct(SqsClient $sqs)
    {
        $this->sqs = $sqs;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new SqsTransport($this->sqs, $serializer, $dsn, $options['message_group_id'] ?? null);
    }

    public function supports(string $dsn, array $options): bool
    {
        return preg_match('#^https://sqs\.[\w\-]+\.amazonaws\.com/.+#', $dsn) === 1;
    }
}
