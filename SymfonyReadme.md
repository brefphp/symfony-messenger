
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

```yaml
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