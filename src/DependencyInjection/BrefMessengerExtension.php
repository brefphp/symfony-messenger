<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\DependencyInjection;

use Bref\Symfony\Messenger\Service\Sqs\SqsTransportFactory;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class BrefMessengerExtension extends Extension implements CompilerPassInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('messenger.transport.sqs.factory')) {
            $definition = new Definition(SqsTransportFactory::class);
            $definition->setDecoratedService('messenger.transport.sqs.factory');
            $definition->addArgument('@.inner');
        }
    }
}
