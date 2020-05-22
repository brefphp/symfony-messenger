<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\Sqs;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class SqsTransportFactory implements TransportFactoryInterface
{
    /** @var ContainerInterface */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $sqs = $this->container->get('bref.messenger.sqs_client');

        return new SqsTransport($sqs, $serializer, $dsn, $options['message_group_id'] ?? null);
    }

    public function supports(string $dsn, array $options): bool
    {
        return preg_match('#^https://sqs\.[\w\-]+\.amazonaws\.com/.+#', $dsn) === 1;
    }
}
