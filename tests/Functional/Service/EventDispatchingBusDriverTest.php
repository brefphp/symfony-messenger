<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Functional\Service;

use Bref\Symfony\Messenger\Service\BusDriver;
use Bref\Symfony\Messenger\Service\EventDispatchingBusDriver;
use Bref\Symfony\Messenger\Test\Functional\BaseFunctionalTest;
use Nyholm\BundleTest\TestKernel;

class EventDispatchingBusDriverTest extends BaseFunctionalTest
{
    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel([
            'config' => static function (TestKernel $kernel) {
                $kernel->addTestConfig(dirname(__DIR__, 2).'/Resources/config/event_dispatching_bus_driver.yaml');
            },
        ]);
    }

    public function test the bus driver can be swapped(): void
    {
        $this->assertInstanceOf(EventDispatchingBusDriver::class, self::getContainer()->get(BusDriver::class));
    }
}
