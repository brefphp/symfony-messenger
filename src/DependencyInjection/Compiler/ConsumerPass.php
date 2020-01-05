<?php

declare(strict_types=1);

namespace Bref\Messenger\DependencyInjection\Compiler;

use Bref\Messenger\Service\BrefWorker;
use Bref\Messenger\Service\ConsumerProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ConsumerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->has(BrefWorker::class)) {
            return;
        }

        $worker = $container->findDefinition(BrefWorker::class);
        $taggedServices = $container->findTaggedServiceIds('bref_messenger.consumer');
        if (1 === count($taggedServices)) {
            // lets make things simple. Everything that comes in, goes to this consumer.
            $id = array_key_first($taggedServices);
            $worker->replaceArgument(1, new Reference($id));

            return;
        }

        $consumers = [];
        foreach ($taggedServices as $id => $tags) {
            // Get the type from the service and add them to the $consumers array
            $def = $container->getDefinition($id);
            $class = $def->getClass();
            while ($class === null) {
                $def = $container->getDefinition($def->getParent());
                $class = $def->getClass();
            }
            foreach ($class::supportedTypes() as $type) {
                $consumers[$type] = new Reference($id);
            }
        }

        // TODO use a service locator
        $container->findDefinition(ConsumerProvider::class)->replaceArgument(0, $consumers);
    }

}