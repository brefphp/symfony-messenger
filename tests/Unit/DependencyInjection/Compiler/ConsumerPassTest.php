<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Unit\DependencyInjection\Compiler;

use Bref\Symfony\Messenger\DependencyInjection\Compiler\ConsumerPass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ConsumerPassTest extends AbstractCompilerPassTestCase
{
    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ConsumerPass);
    }
}
