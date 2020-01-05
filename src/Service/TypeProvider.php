<?php declare(strict_types=1);

namespace Bref\Messenger\Service;

/**
 * Read the event type from most/all AWS events.
 */
final class TypeProvider implements TypeResolver
{
    /** @var TypeResolver[] */
    private $typeResolvers;

    public function __construct(iterable $typeResolvers)
    {
        $this->typeResolvers = $typeResolvers;
    }

    public function getType(array $event): ?string
    {
        foreach ($this->typeResolvers as $typeResolver) {
            $type = $typeResolver->getType($event);
            if ($type !== null) {
                return $type;
            }
        }

        // If the $event looks like a standard AWS event, try to fetch the "eventSource" property of the first Record.
        if (is_array($event) && isset($event['Records'])) {
            $key = array_key_first($event['Records']);
            $source = $event['Records'][$key]['EventSource'] ?? $event['Records'][$key]['eventSource'] ?? null;
            if ($source !== null && preg_match('|^aws:([a-z0-9]+)$|i', (string) $source, $match) === 1) {
                return strtolower($match[1]);
            }
        }

        return null;
    }
}
