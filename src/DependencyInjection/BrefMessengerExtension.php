<?php declare(strict_types=1);

namespace Bref\Messenger\DependencyInjection;

use Bref\Messenger\Service\AbstractConsumer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BrefMessengerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        foreach ($config['consumers'] as $transportName => $consumerConfig) {
            $definition = new ChildDefinition($consumerConfig['service']);
            $definition->addTag('bref_messenger.consumer');

            // Add parameters to it.
            if (is_subclass_of($consumerConfig['service'], AbstractConsumer::class)) {
                $definition->replaceArgument(2, new Reference($consumerConfig['bus_service']));
                $definition->replaceArgument(3, $transportName);
                if ($consumerConfig['use_symfony_retry_strategies']) {
                    $definition->replaceArgument(4, new Reference(EventDispatcherInterface::class));
                } else {
                    $definition->replaceArgument(4, null);
                }
            }
            $container->setDefinition('bref.messenger.consumer_'.$transportName, $definition);
        }

    }
}
