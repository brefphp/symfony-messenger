<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service;

/**
 * An implementing class should look at the $event and return a string type like "sqs", "sns", etc
 * If type cannot be determined, the null should be returned.
 */
interface TypeResolver
{
    /**
     * @param array $event input from AWS
     */
    public function getType(array $event): ?string;
}
