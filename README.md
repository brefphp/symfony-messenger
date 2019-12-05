Bridge to use Symfony Messenger with SQS on AWS Lambda with [Bref](https://bref.sh).

## Introduction

TODO

## Installation

This guide assumes that:

- Symfony is installed
- Symfony Messenger is installed
- Bref is installed and [configured to deploy Symfony](https://bref.sh/docs/frameworks/symfony.html)
- a SQS queue has already been created

First, install this package:

```
composer require bref/symfony-messenger-sqs
```

Next, register the bundle in `config/bundles.php`:

```php
return [
    ...
    Bref\Messenger\BrefMessengerBundle::class => ['all' => true],
];
```

Next, configure Symfony Messenger to dispatch a message via SQS:

```yaml
# config/packages/messenger.yaml

framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
             'App\Message\MyMessage': async
```

Here, the `MyMessage` class will be dispatch to the `async` transport. We can now configure the `async` transport to use our SQS queue.

To do that, let's configure the `MESSENGER_TRANSPORT_DSN` environment variable to contain the URL of the queue:

```dotenv
MESSENGER_TRANSPORT_DSN=https://sqs.us-east-1.amazonaws.com/123456789101/my-queue
```

### Sending messages

Now that Messenger is configured with SQS, we can send messages using the `MessageBusInterface`. For example, in a controller:

```php
class DefaultController extends AbstractController
{
    public function index()
    {
        $this->dispatchMessage(new App\Message\MyMessage());
    }
}
```

Read [the Symfony documentation to learn more](https://symfony.com/doc/current/messenger.html#dispatching-the-message).

### Processing message

Messages are sent to SQS, we now need to process those messages asynchronously.

We can create a Lambda to do that in `serverless.yml`:

```yaml
functions:
    worker:
        handler: consumer.php
        timeout: 120 # in seconds
        reservedConcurrency: 5 # max. 5 messages processed in parallel
        layers:
            - ${bref:layer.php-73}
        events:
            -   sqs:
                    arn: arn:aws:sqs:us-east-1:123456789101:my-queue
                    # Only 1 item at a time to simplify error handling
                    batchSize: 1
```

The Lambda handler will be `consumer.php`, a file we must create:

```php
<?php declare(strict_types=1);

use Bref\Messenger\Sqs\SqsConsumer;

require __DIR__ . '/config/bootstrap.php';

lambda(function ($event) {
    $kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
    $kernel->boot();

    $sqsConsumer = $kernel->getContainer()->get(SqsConsumer::class);
    $sqsConsumer->consumeLambdaEvent($event);
});
```

Finally, we must configure the `SqsConsumer` service in `config/services.yaml` (this configuration relies on autowiring being enabled by default):

```yaml
services:
    ...

    Bref\Messenger\Sqs\SqsConsumer:
        arguments:
            # Inject the transport name used in config/packages/messenger.yaml 
            $transportName: 'async'
        public: true
```
