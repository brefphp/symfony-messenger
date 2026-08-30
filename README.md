Bridge to use Symfony Messenger on AWS Lambda with [Bref](https://bref.sh).

This bridge allows messages to be dispatched to SQS, SNS or EventBridge, while workers handle those messages on AWS Lambda.

## Documentation

You can find the documentation [on the Bref website here](https://bref.sh/docs/symfony/messenger).

## Worker events

By default messages are put on the bus by `SimpleBusDriver`, which does not dispatch
Symfony's worker events. Symfony implements retries, the failure transport and most
monitoring integrations as listeners on those events, so none of them run on Lambda
with the default driver.

`EventDispatchingBusDriver` dispatches `WorkerMessageReceivedEvent`,
`WorkerMessageHandledEvent` and `WorkerMessageFailedEvent` like `messenger:consume`
does. Enable it by aliasing the `BusDriver` service:

```yaml
services:
    Bref\Symfony\Messenger\Service\BusDriver: '@Bref\Symfony\Messenger\Service\EventDispatchingBusDriver'
```
