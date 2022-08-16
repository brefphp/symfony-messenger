<?php

namespace Bref\Symfony\Messenger\Event\Sqs;

interface WithMessageGroupId
{
    public function messageGroupId(): string;

}