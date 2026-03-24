<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\DependencyInjection;

use AsyncAws\Scheduler\SchedulerClient;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class BrefMessengerExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        if (class_exists(SchedulerClient::class)) {
            $container->setDefinition('bref.messenger.scheduler_client', new Definition(SchedulerClient::class));

            $container->getDefinition('Bref\Symfony\Messenger\Service\EventBridge\EventBridgeTransportFactory')
                ->setArgument(1, new Reference('bref.messenger.scheduler_client'));

            $container->setDefinition('Bref\Symfony\Messenger\Service\EventBridge\ScheduleDeleter', (new Definition('Bref\Symfony\Messenger\Service\EventBridge\ScheduleDeleter'))
                ->setArgument(0, new Reference('bref.messenger.scheduler_client')));
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $frameworkConfig = $container->getExtensionConfig('framework');
        $messengerTransports = $this->getMessengerTransports($frameworkConfig);

        $container->setParameter('messenger.transports', $messengerTransports);
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
