<?php

declare(strict_types=1);

namespace Bref\Messenger\DependencyInjection;


use Bref\Messenger\Service\Sqs\SqsConsumer;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('bref_messenger');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('foo')->isRequired()->cannotBeEmpty()->end()
            ->end()
            ->append($this->addConsumesNode())
        ->end();

        return $treeBuilder;
    }

    private function addConsumesNode()
    {
        $treeBuilder = new TreeBuilder('consumers');

        $node = $treeBuilder->getRootNode()
            ->isRequired()
            ->requiresAtLeastOneElement()
            ->fixXmlConfig('consumer')
            ->useAttributeAsKey('name')
            ->info('The transport name as key.')
            ->arrayPrototype()
                ->children()
                    ->scalarNode('service')->isRequired()->info('The service that should handle the event')->example(SqsConsumer::class)->end()
                    ->scalarNode('bus_service')->defaultValue('messenger.routable_message_bus')->info('The bus to use when consuming messages.')->end()
                    ->scalarNode('serializer_service')->defaultValue(SerializerInterface::class)->info('The serializer to use when consuming messages.')->end()
                    ->booleanNode('use_symfony_retry_strategies')->defaultFalse()->info('If enabled, retries are handled like in a normal Symfony application. When disabled, AWS will handle the reties.')->end()
                ->end()
            ->end()
        ;

        return $node;
    }
}
