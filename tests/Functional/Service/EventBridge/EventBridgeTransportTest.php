<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Functional\Service\EventBridge;

use Bref\Symfony\Messenger\Service\EventBridge\EventBridgeTransport;
use Bref\Symfony\Messenger\Service\EventBridge\EventBridgeTransportFactory;
use Bref\Symfony\Messenger\Test\Functional\BaseFunctionalTest;
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
        $factory = $this->container->get('bref_messenger.transport.event_bridge');
        $this->assertInstanceOf(EventBridgeTransportFactory::class, $factory);

        $this->assertTrue($factory->supports('eventbridge://', []));
        $this->assertTrue($factory->supports('eventbridge://myapp.mycomponent', []));
        $this->assertFalse($factory->supports('sns://arn:aws:sns:us-east-1:1234567890:test', []));
        $this->assertFalse($factory->supports('https://example.com', []));
        $this->assertFalse($factory->supports('arn:aws:sns:us-east-1:1234567890:test', []));

        $transport = $factory->createTransport('eventbridge://myapp.mycomponent', [], new PhpSerializer);
        $this->assertInstanceOf(EventBridgeTransport::class, $transport);
    }
}
