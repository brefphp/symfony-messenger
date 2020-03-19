<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger;

use Bref\Symfony\Messenger\DependencyInjection\CompilerPass\BrefTransportFactoryPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BrefMessengerBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new BrefTransportFactoryPass);
    }
}
