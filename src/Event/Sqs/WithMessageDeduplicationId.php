<?php

namespace Bref\Symfony\Messenger\Event\Sqs;

interface WithMessageDeduplicationId
{
    /**
     * Max length of the messageDeduplicationId is 128 characters.
     * Can contain only alphanumeric characters and punctuation.
     * @See https://docs.aws.amazon.com/AWSSimpleQueueService/latest/APIReference/API_SendMessage.html
     */
    public function messageDeduplicationId(): string;

}