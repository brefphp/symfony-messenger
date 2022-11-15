Bridge to use Symfony Messenger on AWS Lambda with [Bref](https://bref.sh).

This bridge allows messages to be dispatched to SQS, SNS or EventBridge, while workers handle those messages on AWS Lambda.

## Installation

This guide assumes that:

- Symfony is installed
- [Symfony Messenger is installed](https://symfony.com/doc/current/messenger.html#installation)
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

SQS, SNS and EventBridge can now be used with Symfony Messenger.

## Usage

Symfony Messenger dispatches messages. To create a message, follow the [Symfony Messenger documentation](https://symfony.com/doc/current/messenger.html#creating-a-message-handler).

To configure **where** messages are dispatched, all the examples in this documentation are based on [the example from the Symfony documentation](https://symfony.com/doc/current/messenger.html#transports-async-queued-messages): 

```yaml
# config/packages/messenger.yaml

framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
             'App\Message\MyMessage': async
```

### SQS

The [SQS](https://aws.amazon.com/sqs/) service is a queue that works similar to RabbitMQ. To use it, set its URL in the environment variable `MESSENGER_TRANSPORT_DSN`:

```dotenv
MESSENGER_TRANSPORT_DSN=https://sqs.us-east-1.amazonaws.com/123456789/my-queue
```

That's it, messages will be dispatched to that queue.

The implementation uses the SQS transport provided by [Symfony Amazon SQS Messenger](https://symfony.com/doc/current/messenger.html#amazon-sqs), 
so all those features are supported. If you already use that transport, the transition to AWS Lamdba is very easy and 
should not require any change for dispatching messages. 

#### Create the SQS queue

You can create the Queue yourself in the Console, write custom Cloudformation 
or use [Lift's Queue construct](https://github.com/getlift/lift/blob/master/docs/queue.md) that will handle that for you. 

Here is a simple example with Lift, make sure to [install the plugin first](https://github.com/getlift/lift#installation) and check out the [full documentation](https://github.com/getlift/lift/blob/master/docs/queue.md) for more details. 

```yaml
# serverless.yml

service: my-app
provider:
    name: aws
    environment:
        ...
        MESSENGER_TRANSPORT_DSN: ${construct:jobs.queueUrl}

constructs:
    jobs:
        type: queue
        worker:
            handler: bin/consumer.php
            timeout: 20 # in seconds
            reservedConcurrency: 5 # max. 5 messages processed in parallel
            layers:
                - ${bref:layer.php-80}

plugins:
    - serverless-lift
```

In all cases, you would want to disable `auto_setup` to avoid extra requests and permission issues.

```yaml
# config/packages/messenger.yaml

framework:
    messenger:
        transports:
            async: 
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    auto_setup: false
```

#### Add permissions

When running Symfony on AWS Lambda, it is not necessary to configure credentials. The AWS client will read them [from environment variables](https://docs.aws.amazon.com/lambda/latest/dg/configuration-envvars.html#configuration-envvars-runtime) automatically.

You just have to provide the correct [role statements](https://www.serverless.com/framework/docs/providers/aws/guide/iam/) in `serverless.yml` and Lambda will take care of the rest. The required IAM permission to publish to SQS using Messenger is `sqs:SendMessage` on the given queue.

If you use Lift, this is done automatically for you.

#### Consume messages from SQS

1. **If you don't use Lift**, create the function that will be invoked by SQS in `serverless.yml`:

```yaml
functions:
    worker:
        handler: bin/consumer.php
        timeout: 20 # in seconds
        reservedConcurrency: 5 # max. 5 messages processed in parallel
        layers:
            - ${bref:layer.php-80}
        events:
            # Read more at https://www.serverless.com/framework/docs/providers/aws/events/sqs/
            - sqs:
                arn: arn:aws:sqs:us-east-1:1234567890:my_sqs_queue
                # Only 1 item at a time to simplify error handling
                batchSize: 1
```

2. Create the handler script (for example `bin/consumer.php`):

```php
<?php declare(strict_types=1);

use Bref\Symfony\Messenger\Service\Sqs\SqsConsumer;

require dirname(__DIR__) . '/config/bootstrap.php';

$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

// Return the Bref consumer service
return $kernel->getContainer()->get(SqsConsumer::class);
```

If you are using Symfony 5.1 or later, use this instead:

```php
<?php declare(strict_types=1);

use Bref\Symfony\Messenger\Service\Sqs\SqsConsumer;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool)$_SERVER['APP_DEBUG']);
$kernel->boot();

// Return the Bref consumer service
return $kernel->getContainer()->get(SqsConsumer::class);
```

3. Register and configure the `SqsConsumer` service:

```yaml
# config/services.yaml
services:
    Bref\Symfony\Messenger\Service\Sqs\SqsConsumer:
        public: true
        autowire: true
        arguments:
            # Pass the transport name used in config/packages/messenger.yaml
            $transportName: 'async'
            # true enables partial SQS batch failure
            # Enabling this without proper SQS config will consider all your messages successful
            # See https://bref.sh/docs/function/handlers.html#partial-batch-response for more details.
            $partialBatchFailure: false
```

Now, anytime a message is dispatched to SQS, the Lambda function will be called. The Bref consumer class will put back the message into Symfony Messenger to be processed.

#### FIFO Queue

The [FIFO queue](https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/FIFO-queues.html) guarantees 
exactly once delivery, and has a mandatory queue name suffix `.fifo`:

```yaml
# config/packages/messenger.yaml

framework:
    messenger:
        transports:
            async: 
                dsn: 'https://sqs.us-east-1.amazonaws.com/123456789/my-queue.fifo'
```

```yaml
# serverless.yml
resources:
    Resources:
        Queue:
            Type: AWS::SQS::Queue
            Properties:
                QueueName: my-queue.fifo
                FifoQueue: true
```

[Symfony Amazon SQS Messenger](https://symfony.com/doc/current/messenger.html#amazon-sqs)  will automatically calculate/set 
the `MessageGroupId` and `MessageDeduplicationId` parameters required for FIFO queues, but you can set them explicitly:

```php
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsFifoStamp;

/* @var MessageBus $messageBus */
$messageBus->dispatch(new MyAsyncMessage(), [new AmazonSqsFifoStamp('my-group-message-id', 'my-deduplication-id')]);
```
Everything else is identical to the normal SQS queue.

### SNS

AWS [SNS](https://aws.amazon.com/sns) is "notification" instead of "queues". Messages may not arrive in the same order as sent, and they might arrive all at once. To use it, create a SNS topic and set it as the DSN:

```dotenv
MESSENGER_TRANSPORT_DSN=sns://arn:aws:sns:us-east-1:1234567890:foobar
```

That's it, messages will be dispatched to that topic.

> Note: when running Symfony on AWS Lambda, it is not necessary to configure credentials. The AWS client will read them [from environment variables](https://docs.aws.amazon.com/lambda/latest/dg/configuration-envvars.html#configuration-envvars-runtime) automatically.

To consume messages from SNS:

1. Create the function that will be invoked by SNS in `serverless.yml`:

```yaml
functions:
    worker:
        handler: bin/consumer.php
        timeout: 20 # in seconds
        reservedConcurrency: 5 # max. 5 messages processed in parallel
        layers:
            - ${bref:layer.php-80}
        events:
            # Read more at https://www.serverless.com/framework/docs/providers/aws/events/sns/
            - sns:
                arn: arn:aws:sns:us-east-1:1234567890:my_sns_topic
```

2. Create the handler script (for example `bin/consumer.php`):

```php
<?php declare(strict_types=1);

use Bref\Symfony\Messenger\Service\Sns\SnsConsumer;

require dirname(__DIR__) . '/config/bootstrap.php';

$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

// Return the Bref consumer service
return $kernel->getContainer()->get(SnsConsumer::class);
```

If you are using Symfony 5.1 or later, use this instead:

```php
<?php declare(strict_types=1);

use Bref\Symfony\Messenger\Service\Sns\SnsConsumer;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

// Return the Bref consumer service
return $kernel->getContainer()->get(SnsConsumer::class);
```

3. Register and configure the `SnsConsumer` service:

```yaml
# config/services.yaml
services:
    Bref\Symfony\Messenger\Service\Sns\SnsConsumer:
        public: true
        autowire: true
        arguments:
            # Pass the transport name used in config/packages/messenger.yaml
            $transportName: 'async'
```

Now, anytime a message is dispatched to SNS, the Lambda function will be called. The Bref consumer class will put back the message into Symfony Messenger to be processed.

### EventBridge

AWS [EventBridge](https://aws.amazon.com/eventbridge/) is a message routing service. It is similar to SNS, but more powerful. To use it, configure the DSN like so:

```dotenv
# "myapp" is the EventBridge "source", i.e. a namespace for your application's messages
# This source name will be reused in `serverless.yml` later.
MESSENGER_TRANSPORT_DSN=eventbridge://myapp
```
Optionally you can add set the [EventBusName](https://docs.aws.amazon.com/eventbridge/latest/APIReference/API_PutEventsRequestEntry.html#eventbridge-Type-PutEventsRequestEntry-EventBusName) via a `event_bus_name` query parameter, either the name or the ARN:
```dotenv
MESSENGER_TRANSPORT_DSN=eventbridge://myapp?event_bus_name=custom-bus
MESSENGER_TRANSPORT_DSN=eventbridge://myapp?event_bus_name=arn:aws:events:us-east-1:123456780912:event-bus/custom-bus
```

That's it, messages will be dispatched to EventBridge.

> Note: when running Symfony on AWS Lambda, it is not necessary to configure credentials. The AWS client will read them [from environment variables](https://docs.aws.amazon.com/lambda/latest/dg/configuration-envvars.html#configuration-envvars-runtime) automatically.

To consume messages from EventBridge:

1. Create the function that will be invoked by EventBridge in `serverless.yml`:

```yaml
functions:
    worker:
        handler: bin/consumer.php
        timeout: 20 # in seconds
        reservedConcurrency: 5 # max. 5 messages processed in parallel
        layers:
            - ${bref:layer.php-80}
        events:
            # Read more at https://www.serverless.com/framework/docs/providers/aws/events/event-bridge/
            -   eventBridge:
                    # In case of you change bus name in config/packages/messenger.yaml (i.e eventbridge://myapp?event_bus_name=custom-bus) you need to set bus name like below
                    # eventBus: custom-bus
                    # This filters events we listen to: only events from the "myapp" source.
                    # This should be the same source defined in config/packages/messenger.yaml
                    pattern:
                        source:
                            - myapp
```

2. Create the handler script (for example `bin/consumer.php`):

```php
<?php declare(strict_types=1);

use Bref\Symfony\Messenger\Service\EventBridge\EventBridgeConsumer;

require dirname(__DIR__) . '/config/bootstrap.php';

$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

// Return the Bref consumer service
return $kernel->getContainer()->get(EventBridgeConsumer::class);
```

If you are using Symfony 5.1 or later, use this instead:

```php
<?php declare(strict_types=1);

use Bref\Symfony\Messenger\Service\EventBridge\EventBridgeConsumer;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

// Return the Bref consumer service
return $kernel->getContainer()->get(EventBridgeConsumer::class);
```

3. Register and configure the `EventBridgeConsumer` service:

```yaml
# config/services.yaml
services:
    Bref\Symfony\Messenger\Service\EventBridge\EventBridgeConsumer:
        public: true
        autowire: true
        arguments:
            # Pass the transport name used in config/packages/messenger.yaml
            $transportName: 'async'
            # Optionnally, if you have different buses in config/packages/messenger.yaml, set $bus like below:
            # $bus: '@event.bus'
```

Now, anytime a message is dispatched to EventBridge for that source, the Lambda function will be called. The Bref consumer class will put back the message into Symfony Messenger to be processed.

## Error handling

AWS Lambda has error handling mechanisms (retrying and handling failed messages). Because of that, this package does not integrates Symfony Messenger's retry mechanism. Instead, it works with Lambda's retry mechanism.

> This section is work in progress, feel free to contribute to improve it.

When a message fails with SQS, by default it will go back to the SQS queue. It will be retried until the message expires. Here is an example to setup retries and "dead letter queue" with SQS:

```yaml
# serverless.yml
resources:
    Resources:
        Queue:
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

When using SNS and EventBridge, messages will be retried by default 2 times.

## Configuration

### Configuring AWS clients

By default, AWS clients (SQS, SNS, EventBridge) are preconfigured to work on AWS Lambda (thanks to [environment variables populated by AWS Lambda](https://docs.aws.amazon.com/lambda/latest/dg/configuration-envvars.html#configuration-envvars-runtime)).

However, it is possible customize the AWS clients, for example to use them outside of AWS Lambda (locally, on EC2…) or to mock them in tests. These clients are registered as Symfony services under the keys:

- `bref.messenger.sqs_client`
- `bref.messenger.sns_client`
- `bref.messenger.eventbridge_client`

For example to customize the SQS client:

```yaml
services:
    bref.messenger.sqs_client:
        class: AsyncAws\Sqs\SqsClient
        public: true # the AWS clients must be public
        arguments:
            # Apply your own config here
            -
                region: us-east-1
```

### Disabling transports

By default, this package registers Symfony Messenger transports for SQS, SNS and EventBridge.

If you want to disable some transports (for example in case of conflict), you can remove `BrefMessengerBundle` from `config/bundles.php` and reconfigure the transports you want in your application's config. Take a look at [`Resources/config/services.yaml`](Resources/config/services.yaml) to copy the part that you want.

### Customizing the serializer

If you want to change how messages are serialized, for example to use [Happyr message serializer](https://github.com/Happyr/message-serializer), you need to add the serializer on both the transport and the consumer. For example:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async: 
                dsn: 'https://sqs.us-east-1.amazonaws.com/123456789/my-queue'
                serializer: 'Happyr\MessageSerializer\Serializer'

# config/services.yaml
services:
    Bref\Symfony\Messenger\Service\Sqs\SqsConsumer:
        public: true
        autowire: true
        arguments:
            $transportName: 'async'
            $serializer: '@Happyr\MessageSerializer\Serializer'
```
