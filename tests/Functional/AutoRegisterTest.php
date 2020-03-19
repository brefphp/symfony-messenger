<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Functional;

use Bref\Symfony\Messenger\BrefMessengerBundle;
use Nyholm\BundleTest\BaseBundleTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Container;

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

    public function test factory minimal config(): void
    {
        $kernel = $this->createKernel();
        $kernel->addConfigFile(dirname(__DIR__) . '/Resources/config/invalid-sqs-minimal.yaml');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('@"bref_messenger.transport.sqs".+SqsClient@');
        $this->bootKernel();
    }
}
