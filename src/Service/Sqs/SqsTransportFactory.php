<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\Sqs;

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Decorator for AmazonSqsTransportFactory until Symfony 5.2 is released.
 */
final class SqsTransportFactory implements TransportFactoryInterface
{
    /** @var TransportFactoryInterface */
    private $factory;

    public function __construct(TransportFactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return $this->factory->createTransport($dsn, $options, $serializer);
    }

    public function supports(string $dsn, array $options): bool
    {
        return $this->factory->supports($dsn, $options) || preg_match('#^https://sqs\.[\w\-]+\.amazonaws\.com/.+#', $dsn);
    }
}
