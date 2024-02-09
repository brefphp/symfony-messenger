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
        $configs = $container->getExtensionConfig('framework');

        foreach (array_reverse($configs) as $config) {
            if (array_key_exists('messenger', $config)) {
                $container->setParameter('messenger.transports', $config['messenger']['transports']);
            }
        }
    }
}
