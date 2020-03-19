<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\DependencyInjection\CompilerPass;

use Bref\Symfony\Messenger\DependencyInjection\TransportProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class BrefTransportFactoryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // find all service IDs with the app.mail_transport tag
        $taggedServices = $container->findTaggedServiceIds('bref_messenger.transport_factory');

        foreach ($taggedServices as $id => $tags) {
            $definition = $container->getDefinition($id);
            $argument = $definition->getArgument(0);
            if (! $argument instanceof Reference) {
                // This is not expected
                continue;
            }

            $explicitConfigured = false;
            foreach ($tags as $tag) {
                $type = $tag['type'];
                $explicitConfigured = $explicitConfigured || ($tag['explicit_configured'] ?? false);
            }

            $clientService = $argument->__toString();
            if ($container->has($clientService)) {
                continue;
            }

            // This service is not valid. We should throw exception or remove it
            if ($explicitConfigured) {
                throw new InvalidConfigurationException(sprintf('You have configured "%s" but the "%s" service is not found. Make sure you have installed "%s" and defined that service. You may also update service name at config "%s.client"', $id, $clientService, TransportProvider::getAllServices()[$type]['package'], $id));
            }

            // Remove
            $container->removeDefinition($id);
        }
    }
}
