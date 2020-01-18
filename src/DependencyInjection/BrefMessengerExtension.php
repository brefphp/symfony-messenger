<?php declare(strict_types=1);

namespace Bref\Messenger\DependencyInjection;

use Bref\Messenger\Service\AbstractConsumer;
use Bref\Messenger\Service\Sns\SnsConsumer;
use Bref\Messenger\Service\Sqs\SqsConsumer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BrefMessengerExtension extends Extension
{
    private $classToServiceDefinitionFile = [
        SnsConsumer::class => 'sns.yaml',
        SqsConsumer::class => 'sqs.yaml',
    ];

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        foreach ($config['consumers'] as $transportName => $consumerConfig) {
            $definition = new ChildDefinition($consumerConfig['service']);
            $definition->addTag('bref_messenger.consumer', ['transport' => $transportName, 'allow_no_transport' => $consumerConfig['no_transport']]);

            if (is_subclass_of($consumerConfig['service'], AbstractConsumer::class)) {
                // Add parameters to it if we know what type of class it is.
                $this->includeServiceDefinition($loader, $consumerConfig['service']);
                $definition->replaceArgument(1, new Reference($consumerConfig['bus']));
                $definition->replaceArgument(2, new Reference($consumerConfig['serializer']));
                $definition->replaceArgument(3, $transportName);
            }
            $container->setDefinition('bref.messenger.consumer_' . $transportName, $definition);
        }
    }

    private function includeServiceDefinition(FileLoader $loader, string $class): void
    {
        if (isset($this->classToServiceDefinitionFile[$class])) {
            $loader->load($this->classToServiceDefinitionFile[$class]);

            // Remove it so we dont load it twice.
            unset($this->classToServiceDefinitionFile[$class]);
        }
    }
}
