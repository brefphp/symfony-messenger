<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Functional;

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Bref\Symfony\Messenger\BrefMessengerBundle;
use Bref\Symfony\Messenger\Test\Resources\TestMessage\TestMessage;
use Nyholm\BundleTest\BaseBundleTestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\MessageBusInterface;

class AutoRegisterTest extends BaseBundleTestCase
{
    /** @var Container */
    protected $container;

    protected function getBundleClass()
    {
        return BrefMessengerBundle::class;
    }

    public function test use factory not defined(): void
    {
        $kernel = $this->createKernel();
        $kernel->addConfigFile(dirname(__DIR__) . '/Resources/config/invalid-factory-client.yaml');
        $this->expectException(InvalidConfigurationException::class);
        $this->bootKernel();
    }

    public function test use missing factory dependencies(): void
    {
        $kernel = $this->createKernel();
        $kernel->addConfigFile(dirname(__DIR__) . '/Resources/config/invalid-factory-client-explicit.yaml');
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('@"bref_messenger.transport.foo".+SqsClient@');
        $this->bootKernel();
    }

    public function test factory custom service(): void
    {
        $kernel = $this->createKernel();
        $kernel->addConfigFile(dirname(__DIR__) . '/Resources/config/sqs-custom-service.yaml');
        $this->bootKernel();

        $this->sendSqs($kernel->getContainer()->get('test.service_container'));
    }

    public function test factory minimal config(): void
    {
        $kernel = $this->createKernel();
        $kernel->addConfigFile(dirname(__DIR__) . '/Resources/config/invalid-sqs-minimal.yaml');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('@"bref_messenger.transport.sqs".+SqsClient@');
        $this->bootKernel();

        $this->sendSqs($kernel->getContainer()->get('test.service_container'));
    }

    private function sendSqs(?object $container)
    {
        /** @var MockHandler $mock */
        $mock = $container->get('mock_handler');
        $queueUrl = '';
        $mock->append(
            function (CommandInterface $cmd, RequestInterface $request) use (&$queueUrl) {
                $queueUrl = (string) $request->getUri();

                return new Result(['MessageId' => 'abcd']);
            }
        );

        /** @var MessageBusInterface $bus */
        $bus = $container->get(MessageBusInterface::class);
        $bus->dispatch(new TestMessage('hello'));

        // Check that the URL is correct
        $this->assertEquals('https://sqs.us-east-1.amazonaws.com/1234567890/bref-test', $queueUrl);
    }
}
