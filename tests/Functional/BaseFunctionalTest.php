<?php declare(strict_types=1);

namespace Bref\Messenger\Test\Functional;

use Bref\Messenger\BrefMessengerBundle;
use Nyholm\BundleTest\BaseBundleTestCase;
use Symfony\Component\DependencyInjection\Container;

abstract class BaseFunctionalTest extends BaseBundleTestCase
{
    /** @var Container */
    protected $container;

    protected function getBundleClass()
    {
        return BrefMessengerBundle::class;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = $this->createKernel();

        // Add some configuration
        $configDir = dirname(__DIR__) . '/Resources/config/';
        foreach ($this->getDefaultConfig() as $file) {
            $kernel->addConfigFile($configDir . $file);
        }

        $this->bootKernel();
        $this->container = $this->getContainer()->get('test.service_container');
    }

    protected function getDefaultConfig(): array
    {
        return [];
    }
}
