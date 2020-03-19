<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Functional\Service\Sns;

use Bref\Symfony\Messenger\Service\Sns\SnsTransport;
use Bref\Symfony\Messenger\Service\Sns\SnsTransportFactory;
use Bref\Symfony\Messenger\Test\Functional\BaseFunctionalTest;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class SnsTransportTest extends BaseFunctionalTest
{
    protected function getDefaultConfig(): array
    {
        return ['sns.yaml'];
    }

    public function test factory(): void
    {
        /** @var SnsTransportFactory $factory */
        $factory = $this->container->get('bref_messenger.transport.sns');
        $this->assertInstanceOf(SnsTransportFactory::class, $factory);

        $this->assertTrue($factory->supports('sns://arn:aws:sns:us-east-1:1234567890:test', []));
        $this->assertFalse($factory->supports('https://example.com', []));
        $this->assertFalse($factory->supports('arn:aws:sns:us-east-1:1234567890:test', []));

        $transport = $factory->createTransport('sns://arn:aws:sns:us-east-1:1234567890:test', [], new PhpSerializer);
        $this->assertInstanceOf(SnsTransport::class, $transport);
    }
}
