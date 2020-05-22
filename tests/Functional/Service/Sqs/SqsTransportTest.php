<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Functional\Service\Sqs;

use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\Sqs\Result\SendMessageResult;
use AsyncAws\Sqs\SqsClient;
use Bref\Symfony\Messenger\Service\Sqs\SqsTransport;
use Bref\Symfony\Messenger\Service\Sqs\SqsTransportFactory;
use Bref\Symfony\Messenger\Test\Functional\BaseFunctionalTest;
use Bref\Symfony\Messenger\Test\Resources\TestMessage\TestMessage;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Messenger\MessageBusInterface;
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
        $factory = $this->container->get(SqsTransportFactory::class);
        $this->assertInstanceOf(SqsTransportFactory::class, $factory);

        $this->assertTrue($factory->supports('https://sqs.us-east-1.amazonaws.com/1234567890/test', []));
        $this->assertFalse($factory->supports('https://example.com', []));

        $transport = $factory->createTransport('https://sqs.us-east-1.amazonaws.com/1234567890/test', [], new PhpSerializer);
        $this->assertInstanceOf(SqsTransport::class, $transport);
    }

    public function test send message(): void
    {
        $sqs = $this->getMockBuilder(SqsClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['sendMessage'])
            ->getMock();
        $sqs->expects($this->once())
            ->method('sendMessage')
            ->with($this->callback(function ($input) {
                $this->assertEquals('https://sqs.us-east-1.amazonaws.com/1234567890/bref-test', $input['QueueUrl']);

                return true;
            }))
            ->willReturn(ResultMockFactory::create(SendMessageResult::class, ['MessageId'=>4711]));
        $this->container->set('bref.messenger.sqs_client', $sqs);

        /** @var MessageBusInterface $bus */
        $bus = $this->container->get(MessageBusInterface::class);
        $bus->dispatch(new TestMessage('hello'));
    }
}
