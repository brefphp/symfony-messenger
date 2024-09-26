<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\EventBridge;

use Symfony\Component\Messenger\Envelope;

class DefaultEventBridgeDetailTypeResolver implements EventBridgeDetailTypeResolver
{
    public function resolveDetailType(Envelope $message): string
    {
        $explodedFQCN = explode('\\', get_class($message->getMessage()));
        
        return end($explodedFQCN);
    }
}
