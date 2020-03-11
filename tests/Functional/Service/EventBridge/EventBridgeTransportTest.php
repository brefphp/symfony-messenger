<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Functional\Service\EventBridge;

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Bref\Symfony\Messenger\Service\EventBridge\EventBridgeTransport;
use Bref\Symfony\Messenger\Service\EventBridge\EventBridgeTransportFactory;
use Bref\Symfony\Messenger\Test\Functional\BaseFunctionalTest;
use Bref\Symfony\Messenger\Test\Resources\TestMessage\TestMessage;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class EventBridgeTransportTest extends BaseFunctionalTest
{
    protected function getDefaultConfig(): array
    {
        return ['eventbridge.yaml'];
    }

    public function test factory(): void
    {
        /** @var EventBridgeTransportFactory $factory */
        $factory = $this->container->get(EventBridgeTransportFactory::class);
        $this->assertInstanceOf(EventBridgeTransportFactory::class, $factory);

        $this->assertTrue($factory->supports('eventbridge://', []));
        $this->assertTrue($factory->supports('eventbridge://myapp.mycomponent', []));
        $this->assertFalse($factory->supports('sns://arn:aws:sns:us-east-1:1234567890:test', []));
        $this->assertFalse($factory->supports('https://example.com', []));
        $this->assertFalse($factory->supports('arn:aws:sns:us-east-1:1234567890:test', []));

        $transport = $factory->createTransport('eventbridge://myapp.mycomponent', [], new PhpSerializer);
        $this->assertInstanceOf(EventBridgeTransport::class, $transport);
    }

    public function test send message(): void
    {
        /** @var MockHandler $mock */
        $mock = $this->container->get('mock_handler');
        $source = '';
        $detailType = '';
        $mock->append(function (CommandInterface $cmd, RequestInterface $request) use (&$source, &$detailType) {
            $body = json_decode((string) $request->getBody(), true);
            $source = $body['Entries'][0]['Source'];
            $detailType = $body['Entries'][0]['DetailType'];

            return new Result(['MessageId' => 'abcd']);
        });

        /** @var MessageBusInterface $bus */
        $bus = $this->container->get(MessageBusInterface::class);
        $bus->dispatch(new TestMessage('hello'));

        $this->assertEquals('myapp.mycomponent', $source);
        $this->assertEquals('Symfony Messenger message', $detailType);
    }
}
