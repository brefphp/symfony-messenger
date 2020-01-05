<?php

declare(strict_types=1);

namespace Bref\Messenger\Service;

use Bref\Messenger\Exception\TypeNotResolvedException;

/**
 * This class can be used to support multiple types in the same application.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class TypeProvider implements TypeResolver
{
    /**
     * @var TypeResolver[]
     */
    private $typeResolvers;

    public function __construct(iterable $typeResolvers)
    {
        $this->typeResolvers = $typeResolvers;
    }

    public function getType(array $event): ?string
    {
        foreach ($this->typeResolvers as $typeResolver) {
            $type = $typeResolver->getType($event);
            if (null !== $type) {
                return $type;
            }
        }

        // If the $event looks like a standard AWS event, try to fetch the "eventSource" property of the first Record.
        if (is_array($event) && isset($event['Records'])) {
            $key = array_key_first($event['Records']);
            $source = $event['Records'][$key]['EventSource'] ?? $event['Records'][$key]['eventSource'] ?? null;
            if (null !== $source && 1 === preg_match('|^aws:([a-z0-9]+)$|i', (string)$source, $match)) {
                return strtolower($match[1]);
            }
        }

        return null;
    }
}