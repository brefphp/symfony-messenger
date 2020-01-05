<?php declare(strict_types=1);

namespace Bref\Messenger\DependencyInjection\Compiler;

use Bref\Messenger\Service\BrefWorker;
use Bref\Messenger\Service\ConsumerProvider;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ConsumerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (! $container->has(BrefWorker::class)) {
            return;
        }

        $worker = $container->findDefinition(BrefWorker::class);
        $taggedServices = $container->findTaggedServiceIds('bref_messenger.consumer');
        if (count($taggedServices) === 1) {
            $id = array_key_first($taggedServices);
            foreach ($taggedServices[$id] as $tag) {
                $this->verifyTransportExists($container, $tag['transport'] ?? '', $tag['allow_no_transport'] ?? false);
            }

            // lets make things simple. Everything that comes in, goes to this consumer.
            $worker->replaceArgument(1, new Reference($id));

            return;
        }

        $consumers = [];
        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $tag) {
                $this->verifyTransportExists($container, $tag['transport'] ?? '', $tag['allow_no_transport'] ?? false);
            }

            // Get the type from the service and add them to the $consumers array
            $def = $container->getDefinition($id);
            $class = $def->getClass();
            while ($class === null) {
                if (! $def instanceof ChildDefinition) {
                    throw new \RuntimeException(sprintf('Could not get class from definition: "%s"', $id));
                }
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

    /**
     * @throws \RuntimeException if no transport exists with this name.
     */
    private function verifyTransportExists(ContainerBuilder $container, string $transportName, bool $allowNoTransport): void
    {
        if (empty($transportName)) {
            throw new \RuntimeException('No "transport" attribute on tag "bref_messenger.consumer"');
        }

        if ($allowNoTransport) {
            return;
        }

        if (! $container->has('messenger.transport.' . $transportName)) {
            throw new \RuntimeException(sprintf('No transport found with name "%s". Maybe you want to set "bref.consumers.%s.no_transport: true"?', $transportName, $transportName));
        }
    }
}
