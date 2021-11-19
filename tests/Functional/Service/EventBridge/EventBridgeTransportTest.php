<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Functional\Service\EventBridge;

use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\EventBridge\Result\PutEventsResponse;
use Bref\Symfony\Messenger\Service\EventBridge\EventBridgeTransport;
use Bref\Symfony\Messenger\Service\EventBridge\EventBridgeTransportFactory;
use Bref\Symfony\Messenger\Test\Functional\BaseFunctionalTest;
use Bref\Symfony\Messenger\Test\Resources\TestMessage\TestMessage;
use Bref\Symfony\Messenger\Test\Resources\TestMessage\TestMessage2;
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
        $this->assertTrue($factory->supports('eventbridge://myapp/mycomponent', []));
        $this->assertTrue($factory->supports('eventbridge://myapp/mycomponent?event_bus_name=custom', []));
        $this->assertTrue($factory->supports('eventbridge://myapp/mycomponent?event_bus_name=arn:aws:events:us-east-1:123456780912:event-bus/custom-bus', []));
        $this->assertFalse($factory->supports('sns://arn:aws:sns:us-east-1:1234567890:test', []));
        $this->assertFalse($factory->supports('https://example.com', []));
        $this->assertFalse($factory->supports('arn:aws:sns:us-east-1:1234567890:test', []));

        $transport = $factory->createTransport('eventbridge://myapp.mycomponent', [], new PhpSerializer);
        $this->assertInstanceOf(EventBridgeTransport::class, $transport);
    }

    public function test send message(): void
    {
        $eventBridgeClient = $this->getMockBuilder(EventBridgeClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['putEvents'])
            ->getMock();
        $eventBridgeClient->expects($this->once())
            ->method('putEvents')
            ->with($this->callback(function ($input) {
                $entry = $input['Entries'][0];
                $this->assertEquals('myapp.mycomponent', $entry['Source']);
                $this->assertEquals('Symfony Messenger message', $entry['DetailType']);

                return true;
            }))
            ->willReturn(ResultMockFactory::create(PutEventsResponse::class, ['FailedEntryCount' => 0]));
        $this->container->set('bref.messenger.eventbridge_client', $eventBridgeClient);

        /** @var MessageBusInterface $bus */
        $bus = $this->container->get(MessageBusInterface::class);
        $bus->dispatch(new TestMessage('hello'));
    }

    public function test send message with event bus name(): void
    {
        $eventBridgeClient = $this->getMockBuilder(EventBridgeClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['putEvents'])
            ->getMock();
        $eventBridgeClient->expects($this->once())
            ->method('putEvents')
            ->with($this->callback(function ($input) {
                $entry = $input['Entries'][0];
                $this->assertEquals('myapp.mycomponent', $entry['Source']);
                $this->assertEquals('Symfony Messenger message', $entry['DetailType']);
                $this->assertEquals('custom', $entry['EventBridgeName']);

                return true;
            }))
            ->willReturn(ResultMockFactory::create(PutEventsResponse::class, ['FailedEntryCount' => 0]));
        $this->container->set('bref.messenger.eventbridge_client', $eventBridgeClient);

        /** @var MessageBusInterface $bus */
        $bus = $this->container->get(MessageBusInterface::class);
        $bus->dispatch(new TestMessage2('hello'));
    }
}
