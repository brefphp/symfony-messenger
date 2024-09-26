<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\EventBridge;

use Symfony\Component\Messenger\Envelope;

interface EventBridgeDetailTypeResolver
{
    public function resolveDetailType(Envelope $message): string;
}
