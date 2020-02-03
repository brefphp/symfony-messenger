Bridge to use Symfony Messenger on AWS Lambda with [Bref](https://bref.sh).

## Installation

This guide assumes that:

- Symfony is installed
- Symfony Messenger is installed
- Bref is installed and [configured to deploy Symfony](https://bref.sh/docs/frameworks/symfony.html)

First, install this package:

```
composer require bref/symfony-messenger
```

Next, register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Bref\Symfony\Messenger\BrefMessengerBundle::class => ['all' => true],
];
```

Now, it is time to choose you the events you are interested in. 

## Configuration

This bundle has Symfony Messenger Transports to publish messages and Consumers
to receive Lambda events from AWS. All Transports are configurable with a DSN and 
the sections below will show you some examples. They will all follow the normal 
Symfony pattern: 

```yaml
# config/packages/messenger.yaml

framework:
    messenger:
        transports:
            async: 
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
             'App\Message\MyMessage': async
```

To consume messages that has been on the queue, you need to use a *consumer* service.

### SQS

The [SQS](https://aws.amazon.com/sqs/) service is a queue that works similar to
RabbitMQ. The AWS console lets you create a SQS queue as a "normal queue" or a
"FIFO queue". 

> Note that environment variables `AWS_REGION`, `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY`
do always exist on Lambda. The AWS client will read `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY`
automatically. 

#### Normal queue

```yaml
# config/packages/messenger.yaml

framework:
    messenger:
        transports:
            my_sqs: 
                dsn: 'https://sqs.us-east-1.amazonaws.com/123456789/my-queue'

bref_messenger:
    sqs: true # Register the SQS transport

services:
    Aws\Sqs\SqsClient:
        factory: [Aws\Sqs\SqsClient, factory]
        arguments:
            - region: '%env(AWS_REGION)%'
              version: '2012-11-05'

    my_sqs_consumer:
        class: Bref\Symfony\Messenger\Service\Sqs\SqsConsumer
        arguments:
            - '@Bref\Symfony\Messenger\Service\BusDriver'
            - '@messenger.routable_message_bus'
            - '@Symfony\Component\Messenger\Transport\Serialization\SerializerInterface'
            - 'my_sqs' # Same as transport name
```

Now, let's create our Lambda handler (for example `bin/consumer.php`):

```php
<?php declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

// Return here the consumer service
return $kernel->getContainer()->get('my_sqs_consumer');
```

And finally let's configure that handler in `serverless.yml`:

```yaml
functions:
    worker:
        handler: bin/consumer.php
        timeout: 20 # in seconds
        reservedConcurrency: 5 # max. 5 messages processed in parallel
        layers:
            - ${bref:layer.php-74}
        events:
            - sqs:
                arn: arn:aws:sqs:us-east-1:1234567890:my_sqs_queue
                # Only 1 item at a time to simplify error handling
                batchSize: 1
```

#### FIFO Queue

The FIFO queue guarantees exactly once delivery. To differentiate messages we must
either configure the FIFO queue to look at a specific parameter in the message, or
let AWS calculate a hash over the message body. The latter is simpler and it is enabled
by using "Content-Based Deduplication". 

We also need to specify what message group we are using. It can be your applications
reverse hostname. 

```yaml
# config/packages/messenger.yaml

framework:
    messenger:
        transports:
            my_sqs_fifo: 
                dsn: 'https://sqs.us-east-1.amazonaws.com/123456789/my-queue.fifo'
                options: { message_group_id: com_example }
```

Everything else is identical to the normal SQS queue.

### SNS

AWS [SNS](https://aws.amazon.com/sns) is "notification" instead of "queues". Messages
may not arrive in the same order as sent and they might arrive all at once. 

> Note that environment variables `AWS_REGION`, `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY`
do always exist on Lambda. The AWS client will read `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY`
automatically. 

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            my_sns: 
                dsn: 'sns://arn:aws:sns:us-east-1:1234567890:foobar'

bref_messenger:
    sns: true # Register the SNS transport

services:
    Aws\Sns\SnsClient:
        factory: [Aws\Sns\SnsClient, factory]
        arguments:
            - region: '%env(AWS_REGION)%'
              version: '2010-03-31'

    my_sns_consumer:
        class: Bref\Symfony\Messenger\Service\Sns\SnsConsumer
        arguments:
            - '@Bref\Symfony\Messenger\Service\BusDriver'
            - '@messenger.routable_message_bus'
            - '@Symfony\Component\Messenger\Transport\Serialization\SerializerInterface'
            - 'my_sns' # Same as transport name
```

Now, let's create our Lambda handler (for example `bin/consumer.php`):

```php
<?php declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

// Return here the consumer service
return $kernel->getContainer()->get('my_sns_consumer');
```

And finally let's configure that handler in `serverless.yml`:

```yaml
functions:
    worker:
        handler: bin/consumer.php
        timeout: 20 # in seconds
        layers:
            - ${bref:layer.php-74}
        events:
            - sns:
                arn: arn:aws:sns:us-east-1:1234567890:my_sns_topic
```

## Error handling

> This section is really raw, feel free to contribute to improve it.

When a message fails with SQS, by default it will go back to the SQS queue. It will be
retied until the end of time. 

If you are using SNS and the handler fails, then your message is forgotten. 

Below is some config to add a deal letter queue. 

```yaml
# serverless.yml

    queue:
        Type: AWS::SQS::Queue
        Properties:
            # This needs to be at least 6 times the lambda function's timeout
            # See https://docs.aws.amazon.com/lambda/latest/dg/with-sqs.html
            VisibilityTimeout: '960'
            RedrivePolicy:
                deadLetterTargetArn: !GetAtt DeadLetterQueue.Arn
                # Jobs will be retried 5 times
                # The number needs to be at least 5 per https://docs.aws.amazon.com/lambda/latest/dg/with-sqs.html
                maxReceiveCount: 5
    # The dead letter queue is a SQS queue that receives messages that failed to be processed
    DeadLetterQueue:
        Type: AWS::SQS::Queue
        Properties:
            # Messages are stored up to 14 days (the max)
            MessageRetentionPeriod: 1209600

```

## Customize the consumer

Each consumer may be configured how ever you want. A good bus to have as default is the
[RoutableMessageBus](https://github.com/symfony/symfony/blob/4.4/src/Symfony/Component/Messenger/RoutableMessageBus.php)
which will automatically find the correct bus depending on your transport name. 

The same applies with the Serializer. You may want to use [Happyr message serializer](https://github.com/Happyr/message-serializer)
for a more reliable API when sending messages between applications. You need to 
add the serializer on both the transport and the consumer. 

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            workqueue: 
                dsn: 'https://sqs.us-east-1.amazonaws.com/123456789/my-queue'
                serializer: 'Happyr\MessageSerializer\Serializer'

bref_messenger:
    sqs: true # Register the SQS transport

services:
    Aws\Sqs\SqsClient:
        factory: [Aws\Sqs\SqsClient, factory]
        arguments:
            - region: '%env(AWS_REGION)%'
              version: '2012-11-05'

    my_sqs_consumer:
        class: Bref\Symfony\Messenger\Service\Sqs\SqsConsumer
        arguments:
            - '@Bref\Symfony\Messenger\Service\BusDriver'
            - '@messenger.routable_message_bus'
            - '@Happyr\MessageSerializer\Serializer'
            - 'workqueue' # Same as transport name

```

## Creating your own consumer

If you want to do your own implementation of a consumer, you can extend `SqsHandler` or `SnsHandler` yourself.

This class may do every crazy thing you may want. Remember that if you want
to share your Consumer implementation, it is a good idea to use `Bref\Symfony\Messenger\Service\BusDriver`

```php
namespace App\Service;

final class MyConsumer extends \Bref\Event\Sqs\SqsHandler
{
    public function handleSqs(SqsEvent $event, Context $context): void
    {
        // ...
        $envelope = $this->serializer->decode(['body' => /* ... */ ]);

        // ...
    }
}
```

## Using more than one consumer

You can, of course, use as many consumers as you want. Sky is the limit!

```yaml
framework:
    messenger:
        buses:
            messenger.bus.command:
                middleware:
                    - validation
                    - doctrine_transaction

            messenger.bus.event:
                default_middleware: allow_no_handlers
                middleware:
                    - validation
                    - doctrine_transaction

        transports:
            failed: 'doctrine://default?queue_name=failed'
            sync: 'sync://'
            workqueue: 'https://sqs.us-east-1.amazonaws.com/123456789/my-queue'
            notification: 'sns://arn:aws:sns:us-east-1:1234567890:foobar'

        routing:
            'App\Message\Ping': workqueue
            'App\Message\Pong': notification

bref_messenger:
    sns: true
    sqs: true

services:
    _defaults:
        autowire: true
    
    my_sqs_consumer:
        class: Bref\Symfony\Messenger\Service\Sqs\SqsConsumer
        arguments:
            $bus: '@messenger.routable_message_bus'
            $transportName: 'workqueue'

    my_sns_consumer:
        class: Bref\Symfony\Messenger\Service\Sns\SnsConsumer
        arguments:
            $bus: '@messenger.routable_message_bus'
            $transportName: 'notification'
```
