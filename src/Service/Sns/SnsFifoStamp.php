<?php

namespace Bref\Symfony\Messenger\Service\Sns;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

class SnsFifoStamp implements NonSendableStampInterface {
    private ?string $messageGroupId;
    private ?string $messageDeduplicationId;

    public function __construct(?string $messageGroupId = null, ?string $messageDeduplicationId = null)
    {
        $this->messageGroupId = $messageGroupId;
        $this->messageDeduplicationId = $messageDeduplicationId;
    }

    public function getMessageGroupId(): ?string
    {
        return $this->messageGroupId;
    }

    public function getMessageDeduplicationId(): ?string
    {
        return $this->messageDeduplicationId;
    }
}