<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\Sns;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class SnsTransportFactory implements TransportFactoryInterface
{
    /** @var ContainerInterface */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $sns = $this->container->get('bref.messenger.sns_client');

        return new SnsTransport($sns, $serializer, substr($dsn, 6));
    }

    public function supports(string $dsn, array $options): bool
    {
        return strpos($dsn, 'sns://arn:aws:sns:') === 0;
    }
}
