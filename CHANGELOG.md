# Change Log

The change log describes what is "Added", "Removed", "Changed" or "Fixed" between each release.

## Unreleased

Added `EventDispatchingBusDriver`, an opt-in `BusDriver` that dispatches the same
`WorkerMessageReceivedEvent`, `WorkerMessageHandledEvent` and `WorkerMessageFailedEvent`
as Symfony's `messenger:consume` worker. This makes retries, the failure transport and
event-based monitoring integrations work when consuming messages on Lambda.
The default driver is unchanged.

## 0.4.2

Added support for [EventBusName](https://docs.aws.amazon.com/eventbridge/latest/APIReference/API_PutEventsRequestEntry.html#eventbridge-Type-PutEventsRequestEntry-EventBusName) with EventBridgeTransport
## 0.4.0

Use the SQS transport provided by [Symfony Amazon SQS Messenger](https://symfony.com/doc/current/messenger.html#amazon-sqs).
See [UPGRADE-0.4.md](UPGRADE-0.4.md)
## 0.1.0

First release