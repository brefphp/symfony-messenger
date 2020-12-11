UPGRADE to 0.4.0
================

Since version 0.4.0 this package uses the transport provided by [Symfony Amazon SQS Messenger](https://symfony.com/doc/current/messenger.html#amazon-sqs) 
and comes with these BC breaks:

Message Headers
---------------
>  This does not affect transports that have the default `PhpSerializer` 

Previously the message headers were combined in a [single MessageAttribute](https://github.com/brefphp/symfony-messenger/blob/0.3.4/src/Service/Sqs/SqsTransport.php#L46),
now each header is a [separate MessageAttribute](https://github.com/symfony/amazon-sqs-messenger/blob/v5.2.0/Transport/Connection.php#L310). 
This means that the SQS records are incompatible between bref/symfony-messenger 0.3.x and 0.4.0.


Fifo Queues
-----------
The setup for Fifo queues has changed; from transport configuration to a `Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsFifoStamp` 

Before:
```yaml
# config/packages/messenger.yaml

framework:
    messenger:
        transports:
            async: 
                dsn: 'https://sqs.us-east-1.amazonaws.com/123456789/my-queue.fifo'
                options: 
                    message_group_id: com_example # This option is now invalid
```
After:
```php
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsFifoStamp;

/* @var MessageBus $messageBus */
$messageBus->dispatch(new MyAsyncMessage(), [new AmazonSqsFifoStamp('com_example')]);
```