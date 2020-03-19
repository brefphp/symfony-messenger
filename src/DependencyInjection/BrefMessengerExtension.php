<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class BrefMessengerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $usedServices = $this->registerConfiguredServices($container, $config);
        $this->registerInstalledServices($container, $config, $usedServices);
    }

    private function registerConfiguredServices(ContainerBuilder $container, array $config): array
    {
        $availableServices = TransportProvider::getAllServices();
        $usedServices = [];

        foreach ($config['transports'] as $name => $data) {
            $transportFactory = $availableServices[$data['type']]['transport_factory'];
            $client = $availableServices[$data['type']]['default_client'];
            if (\array_key_exists('client', $data)) {
                $client = $data['client'];
            }

            $registerService = $config['register_service'];
            if (\array_key_exists('register_service', $data)) {
                $registerService = $data['register_service'];
            }

            if ($registerService) {
                $usedServices[$name] = $client;
                $this->addServiceDefinition($container, $name, $transportFactory, $client, true, $data['type']);
            } else {
                $usedServices[$name] = null;
            }
        }

        return $usedServices;
    }

    private function registerInstalledServices(ContainerBuilder $container, array $config, array $usedServices): void
    {
        if (! $config['register_service']) {
            return;
        }

        $availableServices = TransportProvider::getAllServices();
        foreach ($availableServices as $name => $data) {
            if (\array_key_exists($name, $usedServices)) {
                continue;
            }

            $this->addServiceDefinition($container, $name, $data['transport_factory'], $data['default_client'], false, $name);
        }
    }

    private function addServiceDefinition(ContainerBuilder $container, string $name, string $factoryClass, string $clientClass, bool $explicitConfigured, string $type): void
    {
        $definition = new Definition($factoryClass);
        $definition->addTag('messenger.transport_factory');
        $definition->addTag('bref_messenger.transport_factory', ['explicit_configured' => $explicitConfigured, 'type' => $type]);
        $definition->addArgument(new Reference($clientClass));

        $container->setDefinition(sprintf('bref_messenger.transport.%s', $name), $definition);
    }
}
