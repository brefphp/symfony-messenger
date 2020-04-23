<?php

namespace Bref\Symfony\Messenger\Service\Sqs;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

final class SqsReceivedStamp implements NonSendableStampInterface
{
    private string $receiptHandle;

    public function __construct(string $receiptHandle)
    {
        $this->receiptHandle = $receiptHandle;
    }

    public function getReceiptHandle(): string
    {
        return $this->receiptHandle;
    }
}
