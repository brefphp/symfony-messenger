<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Functional\Service\Sns;

use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\Sns\Result\PublishResponse;
use AsyncAws\Sns\SnsClient;
use Bref\Symfony\Messenger\Service\Sns\SnsTransport;
use Bref\Symfony\Messenger\Service\Sns\SnsTransportFactory;
use Bref\Symfony\Messenger\Test\Functional\BaseFunctionalTest;
use Bref\Symfony\Messenger\Test\Resources\TestMessage\TestMessage;
use Symfony\Component\Messenger\MessageBusInterface;
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
        $factory = $this->container->get(SnsTransportFactory::class);
        $this->assertInstanceOf(SnsTransportFactory::class, $factory);

        $this->assertTrue($factory->supports('sns://arn:aws:sns:us-east-1:1234567890:test', []));
        $this->assertFalse($factory->supports('https://example.com', []));
        $this->assertFalse($factory->supports('arn:aws:sns:us-east-1:1234567890:test', []));

        $transport = $factory->createTransport('sns://arn:aws:sns:us-east-1:1234567890:test', [], new PhpSerializer);
        $this->assertInstanceOf(SnsTransport::class, $transport);
    }

    public function test send message(): void
    {
        $sns = $this->getMockBuilder(SnsClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['publish'])
            ->getMock();
        $sns->expects($this->once())
            ->method('publish')
            ->with($this->callback(function ($input) {
                $this->assertEquals('arn:aws:sns:us-east-1:1234567890:test', $input['TopicArn']);

                return true;
            }))
            ->willReturn(ResultMockFactory::create(PublishResponse::class, ['MessageId' => 4711]));
        $this->container->set('bref.messenger.sns_client', $sns);

        /** @var MessageBusInterface $bus */
        $bus = $this->container->get(MessageBusInterface::class);
        $bus->dispatch(new TestMessage('hello'));
    }
}
