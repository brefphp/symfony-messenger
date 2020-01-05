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
    Bref\Messenger\BrefMessengerBundle::class => ['all' => true],
];
```

We need to create a new Lambda handler that receives all events from AWS. Lets 
create `bin/consumer.php` with the following contents:

```php
<?php declare(strict_types=1);

use Bref\Messenger\Service\BrefWorker;

require dirname(__DIR__) . '/config/bootstrap.php';

lambda(function ($event) {
    $kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
    $kernel->boot();

    $worker = $kernel->getContainer()->get(BrefWorker::class);
    $worker->consumeLambdaEvent($event);
});
```

Now there is time to choose you the events you are interested in. 

## Configure

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
    consumers:
        my_sqs:
            service: 'Bref\Messenger\Service\Sqs\SqsConsumer'

services:
  Aws\Sqs\SqsClient:
    factory: [Aws\Sqs\SqsClient, factory]
    arguments:
      - region: '%env(AWS_REGION)%'
        version: '2012-11-05'
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

bref_messenger:
    consumers:
        my_sqs_fifo:
            service: 'Bref\Messenger\Service\Sqs\SqsConsumer'

services:
  Aws\Sqs\SqsClient:
    factory: [Aws\Sqs\SqsClient, factory]
    arguments:
      - region: '%env(AWS_REGION)%'
        version: '2012-11-05'
```

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
                dsn: 'sns://arn:aws:sns:us-east-1:403367587399:foobar'

bref_messenger:
    consumers:
        my_sns:
            service: 'Bref\Messenger\Service\Sns\SnsConsumer'

services:
  Aws\Sns\SnsClient:
    factory: [Aws\Sns\SnsClient, factory]
    arguments:
      - region: '%env(AWS_REGION)%'
        version: '2010-03-31'
```

### S3

The [S3](https://aws.amazon.com/s3/) integration is only a Consumer. That means that
we will not be able to publish Symfony Messenger messages on S3 but we can get
notified when a file is uploaded/changed. 

```yaml
# config/packages/messenger.yaml
bref_messenger:
    consumers:
        s3:
            service: 'Bref\Messenger\Service\S3\S3Consumer'
```

```php

namespace App\Message;

use Bref\Messenger\Message\S3Event;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class S3Handler implements MessageHandlerInterface
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(S3Event $event)
    {
        $this->logger->alert('Got S3 event');
        $this->logger->alert(json_encode($event->getRecord()));
    }
}
```

## Serverless configuration

The Serverless configuration is same of all kind Lambda events. You should just 
make sure to configure the parameters correctly. You may also add as many events
as you want. Same type or different types. Mix all you want. 

```yaml
# serverless.yml
functions:
    worker:
        handler: bin/consumer.php
        timeout: 20 # in seconds
        reservedConcurrency: 5 # max. 5 messages processed in parallel
        layers:
            - ${bref:layer.php-74}
        events:
            -   sqs:
                    arn: arn:aws:sqs:us-east-1:1234567890:my_sqs_queue
                    # Only 1 item at a time to simplify error handling
                    batchSize: 1

            -   sqs:
                    arn: arn:aws:sqs:us-east-1:1234567890:my_sqs_queue.fifo
                    batchSize: 1

            -   sns:
                  arn: arn:aws:sns:us-east-1:1234567890:my_sns_topic

            -   s3:
                  bucket: my-test-bucket
                  event: s3:ObjectCreated:*
                  existing: true

```            

## Error handling

### The Symfony way

On each consumer you can choose to let Symfony handle failures as described in
[the documentation](https://symfony.com/doc/current/messenger.html#retries-failures). 
Example: 

```yaml
# config/packages/messenger.yaml

framework:
    messenger:
        transports:
            failed: 'doctrine://default?queue_name=failed'
            workqueue:
              dsn: 'https://sqs.us-east-1.amazonaws.com/123456789/my-queue'
              retry_strategy:
                  max_retries: 3
                  # milliseconds delay
                  delay: 1000
                  multiplier: 2
                  max_delay: 60

bref_messenger:
    consumers:
        my_sqs:
            service: 'Bref\Messenger\Service\Sqs\SqsConsumer'
            use_symfony_retry_strategies: true # default value

# ...

```

The delay is only supported on SQS "normal queue". If you are using SNS or SQS FIFO
you should use the failure queue directly.

```yaml
# config/packages/messenger.yaml

framework:
    messenger:
        transports:
            failed: 'doctrine://default?queue_name=failed'
            workqueue:
              dsn: 'https://sqs.us-east-1.amazonaws.com/123456789/my-queue'

bref_messenger:
    consumers:
        my_sqs:
            service: 'Bref\Messenger\Service\Sqs\SqsConsumer'
            use_symfony_retry_strategies: true # default value

# ...

```

Make sure you re-run the failure queue time to time:

```
# serverless.yml

functions:
    website:
        # ...
    consumer:
        # ...

    console:
        handler: bin/console
        Timeout: 120 # in seconds
        layers:
            - ${bref:layer.php-74}
            - ${bref:layer.console}
        events:
            - schedule:
                  rate: rate(30 minutes)
                  input:
                      cli: messenger:consume failed --time-limit=60 --limit=50

```

### The AWS way

> This section is really raw, feel free to contribute to improve it.

Alternative to the "Symfony way" you may allow AWS infrastructure to handle errors:

```yaml
# config/packages/messenger.yaml

framework:
    messenger:
        transports:
            workqueue:
              dsn: 'https://sqs.us-east-1.amazonaws.com/123456789/my-queue'

bref_messenger:
    consumers:
        my_sqs:
            service: 'Bref\Messenger\Service\Sqs\SqsConsumer'
            use_symfony_retry_strategies: false
# ...

```

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

Each consumer can be configured with a bus and serializer. The default bus is the
[RoutableMessageBus](https://github.com/symfony/symfony/blob/4.4/src/Symfony/Component/Messenger/RoutableMessageBus.php)
which will automatically find the correct bus depending on your transport name. 
You may provide any service that implements `Symfony\Component\Messenger\MessageBusInterface`.


```yaml
# config/packages/messenger.yaml

bref_messenger:
    consumers:
        my_sqs:
            service: 'Bref\Messenger\Service\Sqs\SqsConsumer'
            bus: 'messenger.bus.command'
# ...

```

Same thing with the Serializer. You may want to use [Happyr message serializer](https://github.com/Happyr/message-serializer)
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
    consumers:
        my_sqs:
            service: 'Bref\Messenger\Service\Sqs\SqsConsumer'
            serializer: 'Happyr\MessageSerializer\Serializer'
# ...

```

## Creating your own consumer

If you want to do your own implementation of a consumer

## Using more than one consumer