<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\DependencyInjection\CompilerPass;

use Bref\Symfony\Messenger\DependencyInjection\TransportProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The goal of this compiler pass is to look at all configured DSN and make sure
 * they all are supported. Ie, to avoid errors in runtime.
 */
class BrefTransportFactoryPass implements CompilerPassInterface
{
    /** @var array|null */
    private $dsn;

    public function process(ContainerBuilder $container): void
    {
        // find all service IDs with the app.mail_transport tag
        $taggedServices = $container->findTaggedServiceIds('bref_messenger.transport_factory');

        $removedTypes = [];
        $configuredTypes = [];

        foreach ($taggedServices as $id => $tags) {
            $definition = $container->getDefinition($id);
            $argument = $definition->getArgument(0);
            if (! $argument instanceof Reference) {
                // This is not expected
                continue;
            }

            $explicitConfigured = false;
            foreach ($tags as $tag) {
                $type = $tag['type'] ?? 'unknown';
                $explicitConfigured = $explicitConfigured || ($tag['explicit_configured'] ?? false);
            }

            $clientService = $argument->__toString();
            if ($container->has($clientService)) {
                $configuredTypes[$type] = $definition->getClass();
                continue;
            }

            // This service is not valid. We should throw exception or remove it
            if ($explicitConfigured) {
                throw new InvalidConfigurationException(sprintf('You have configured "%s" but the "%s" service is not found. Make sure you have installed "%s" and defined that service. You may also update service name at config "%s.client"', $id, $clientService, TransportProvider::getAllServices()[$type]['package'], $id));
            }

            // Remove
            $container->removeDefinition($id);
            $removedTypes[$type] = $definition->getClass();
        }

        // Try to resolve all DSN
        foreach ($configuredTypes as $type => $factoryClass) {
            $this->removeResolvedDsn($container, $factoryClass);
        }

        foreach ($removedTypes as $type => $factoryClass) {
            if ((! isset($configuredTypes[$type]) || ! $configuredTypes[$type]) && $this->ifUsed($container, $factoryClass)) {
                // Make sure user get an error on build time instead of runtime
                throw new InvalidConfigurationException(sprintf('It seams like you have configured a messenger transport to use "%s" but no transport factory is registered. Try adding "%s: ~" or disable this message with "%s.register_service: false".', $type, $id, $id));
            }
        }
    }

    /**
     * Look at all Symfony framework.messenger.transport to see if this factory was used.
     */
    private function ifUsed(ContainerBuilder $container, string $factoryClass): bool
    {
        $reflectionClass = new \ReflectionClass($factoryClass);
        $object = $reflectionClass->newInstanceWithoutConstructor();
        $method = $reflectionClass->getMethod('supports');

        foreach ($this->getAllUnresolvedDsn($container) as $dsn) {
            if ($method->invoke($object, $dsn, [])) {
                return true;
            }
        }

        return false;
    }

    private function removeResolvedDsn(ContainerBuilder $container, string $factoryClass): void
    {
        $reflectionClass = new \ReflectionClass($factoryClass);
        $object = $reflectionClass->newInstanceWithoutConstructor();
        $method = $reflectionClass->getMethod('supports');

        foreach ($this->getAllUnresolvedDsn($container) as $i => $dsn) {
            if ($method->invoke($object, $dsn, [])) {
                unset($this->dsn[$i]);
            }
        }
    }

    private function getAllUnresolvedDsn(ContainerBuilder $container): array
    {
        if (is_array($this->dsn)) {
            return $this->dsn;
        }

        $taggedServices = $container->findTaggedServiceIds('messenger.receiver');

        $this->dsn = [];
        foreach ($taggedServices as $id => $tags) {
            $this->dsn[] = $container->getDefinition($id)->getArgument(0);
        }

        return $this->dsn;
    }
}
