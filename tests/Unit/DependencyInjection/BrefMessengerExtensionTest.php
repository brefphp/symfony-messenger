<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Unit\DependencyInjection;

use Bref\Symfony\Messenger\DependencyInjection\BrefMessengerExtension;
use Bref\Symfony\Messenger\Service\SimpleBusDriver;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

class BrefMessengerExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [new BrefMessengerExtension];
    }

    public function testNoConfigIsValid()
    {
        $this->load();

        $this->assertContainerBuilderHasService(SimpleBusDriver::class);
    }
}
