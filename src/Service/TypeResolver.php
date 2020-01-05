<?php

declare(strict_types=1);

namespace Bref\Messenger\Service;

/**
 * An implementing class should look at the $event and return a string type like "sqs", "sns", etc
 * If type cannot be determined, the null should be returned.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
interface TypeResolver
{
    /**
     * @param array $event input from AWS
     * @return string|null
     */
    public function getType(array $event): ?string;
}