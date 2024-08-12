<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class BrefMessengerExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $frameworkConfig = $container->getExtensionConfig('framework');
        $messengerTransports = $this->getMessengerTransports($frameworkConfig);

        if (! empty($messengerTransports)) {
            $container->setParameter('messenger.transports', $messengerTransports);
        }
    }

    private function getMessengerTransports(array $frameworkConfig): array
    {
        $transportConfigs = array_column(
            array_column($frameworkConfig, 'messenger'),
            'transports',
        );
        $transportConfigs = array_filter($transportConfigs);

        if (empty($transportConfigs)) {
            return [];
        }

        return array_merge(...$transportConfigs);
    }
}
