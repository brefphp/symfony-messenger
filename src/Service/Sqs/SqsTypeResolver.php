<?php

declare(strict_types=1);

namespace Bref\Messenger\Service\Sqs;

use Bref\Messenger\Service\AbstractConsumer;
use Bref\Messenger\Service\TypeResolver;

class SqsTypeResolver implements TypeResolver
{
    public function getType(array $event): ?string
    {
        // TODO: Implement getType() method.

        return 'sqs';
    }


}