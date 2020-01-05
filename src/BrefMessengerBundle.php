<?php declare(strict_types=1);

namespace Bref\Messenger;

use Bref\Messenger\DependencyInjection\Compiler\ConsumerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BrefMessengerBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new ConsumerPass());
    }
}
