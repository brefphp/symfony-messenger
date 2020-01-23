<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\Sns;

use Aws\Sns\SnsClient;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class SnsTransportFactory implements TransportFactoryInterface
{
    /** @var SnsClient */
    private $sns;

    public function __construct(SnsClient $sns)
    {
        $this->sns = $sns;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new SnsTransport($this->sns, $serializer, substr($dsn, 6));
    }

    public function supports(string $dsn, array $options): bool
    {
        return strpos($dsn, 'sns://arn:aws:sns:') === 0;
    }
}
