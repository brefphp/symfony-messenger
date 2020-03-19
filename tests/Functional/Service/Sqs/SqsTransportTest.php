<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Functional\Service\Sqs;

use Bref\Symfony\Messenger\Service\Sqs\SqsTransport;
use Bref\Symfony\Messenger\Service\Sqs\SqsTransportFactory;
use Bref\Symfony\Messenger\Test\Functional\BaseFunctionalTest;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class SqsTransportTest extends BaseFunctionalTest
{
    protected function getDefaultConfig(): array
    {
        return ['sqs.yaml'];
    }

    public function test factory(): void
    {
        /** @var SqsTransportFactory $factory */
        $factory = $this->container->get('bref_messenger.transport.sqs');
        $this->assertInstanceOf(SqsTransportFactory::class, $factory);

        $this->assertTrue($factory->supports('https://sqs.us-east-1.amazonaws.com/1234567890/test', []));
        $this->assertFalse($factory->supports('https://example.com', []));

        $transport = $factory->createTransport('https://sqs.us-east-1.amazonaws.com/1234567890/test', [], new PhpSerializer);
        $this->assertInstanceOf(SqsTransport::class, $transport);
    }
}
