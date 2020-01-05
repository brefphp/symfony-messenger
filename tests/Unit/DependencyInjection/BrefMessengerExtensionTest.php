<?php declare(strict_types=1);

namespace Bref\Messenger\Test\Unit\DependencyInjection;

use Bref\Messenger\DependencyInjection\BrefMessengerExtension;
use Bref\Messenger\Service\BrefWorker;
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

        $this->assertContainerBuilderHasService(BrefWorker::class);
    }
}
