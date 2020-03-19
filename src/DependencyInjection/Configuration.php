<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

final class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('bref_messenger');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->fixXmlConfig('transport')
            ->children()
                ->booleanNode('register_service')->info('If set to false, no services will be created.')->defaultTrue()->end()
                ->arrayNode('transports')
                    ->beforeNormalization()->always()->then(\Closure::fromCallable([$this, 'validateType']))->end()
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->booleanNode('register_service')->info('If set to false, no service will be created for this transport.')->defaultTrue()->end()
                            ->enumNode('type')->info('A valid transport type. The service name will be used as default. ')->values(TransportProvider::getServiceNames())->end()
                            ->scalarNode('client')->info('A service name for the service passed to the factory.')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    private static function validateType(?array $clients): array
    {
        if ($clients === null) {
            return [];
        }

        $awsServices = TransportProvider::getServiceNames();
        foreach ($clients as $name => $config) {
            if (\in_array($name, $awsServices)) {
                if (isset($config['type']) && $name !== $config['type']) {
                    throw new InvalidConfigurationException(sprintf('You cannot define a service named "%s" with type "%s". That is super confusing.', $name, $config['type']));
                }
                $clients[$name]['type'] = $name;
            } elseif (! isset($config['type'])) {
                if (! \in_array($name, $awsServices)) {
                    throw new InvalidConfigurationException(sprintf('The "bref_messenger.transport.%s" does not have a type and we were unable to guess it. Please add "bref_messenger.transport.%s.type".', $name, $name));
                }

                $clients[$name]['type'] = $name;
            }
        }

        return $clients;
    }
}
