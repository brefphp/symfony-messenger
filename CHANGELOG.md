# Change Log

The change log describes what is "Added", "Removed", "Changed" or "Fixed" between each release.

## Unreleased

### Added

- **EventBridge Scheduler support**: dispatch messages as one-time or recurring schedules via EventBridge Scheduler.
  - New `SchedulerStamp` to attach scheduling intent to messages (`oneTime()` for one-time, or raw `rate()`/`cron()` expressions for recurring).
  - New `ScheduledStamp` returned on the envelope with the schedule ARN and name.
  - New `ScheduleDeleter` service to cancel/delete schedules.
  - DSN parameters `target_arn`, `role_arn`, and `schedule_group` for transport-level defaults.
  - `async-aws/scheduler` is an optional dependency (`suggest`). Install it with `composer require async-aws/scheduler` to enable scheduling.
  - One-time schedules are auto-deleted by AWS after execution (`ActionAfterCompletion=DELETE`).
  - No breaking changes: existing EventBridge transport behavior is unchanged when no `SchedulerStamp` is used.

## 0.4.2

Added support for [EventBusName](https://docs.aws.amazon.com/eventbridge/latest/APIReference/API_PutEventsRequestEntry.html#eventbridge-Type-PutEventsRequestEntry-EventBusName) with EventBridgeTransport
## 0.4.0

Use the SQS transport provided by [Symfony Amazon SQS Messenger](https://symfony.com/doc/current/messenger.html#amazon-sqs).
See [UPGRADE-0.4.md](UPGRADE-0.4.md)
## 0.1.0

First release