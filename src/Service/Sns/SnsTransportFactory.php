<?php declare(strict_types=1);

namespace Bref\Messenger\Service\Sns;

use Aws\Sns\SnsClient;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class SnsTransportFactory implements TransportFactoryInterface
{
    /** @var SnsClient */
    private $sns;
    /** @var SerializerInterface|null */
    private $serializer;

    public function __construct(SnsClient $sns, ?SerializerInterface $serializer = null)
    {
        $this->sns = $sns;
        $this->serializer = $serializer;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new SnsTransport($this->sns, $this->serializer, $dsn);
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === strpos($dsn, 'sns://arn:aws:sns:');
    }
}
