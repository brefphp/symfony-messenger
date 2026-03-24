<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\EventBridge;

use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\Scheduler\Enum\ActionAfterCompletion;
use AsyncAws\Scheduler\Enum\FlexibleTimeWindowMode;
use AsyncAws\Scheduler\SchedulerClient;
use AsyncAws\Scheduler\ValueObject\FlexibleTimeWindow;
use AsyncAws\Scheduler\ValueObject\Target;
use Exception;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;

final class EventBridgeTransport implements TransportInterface
{
    /** @var SerializerInterface */
    private $serializer;
    /** @var EventBridgeClient */
    private $eventBridge;
    /** @var string */
    private $source;
    /** @var ?string */
    private $eventBusName;
    /** @var ?SchedulerClient */
    private $scheduler;
    /** @var ?string */
    private $scheduleGroup;
    /** @var ?string */
    private $defaultTargetArn;
    /** @var ?string */
    private $defaultRoleArn;

    public function __construct(
        EventBridgeClient $eventBridge,
        SerializerInterface $serializer,
        string $source,
        ?string $eventBusName = null,
        ?SchedulerClient $scheduler = null,
        ?string $scheduleGroup = null,
        ?string $defaultTargetArn = null,
        ?string $defaultRoleArn = null
    ) {
        $this->eventBridge = $eventBridge;
        $this->serializer = $serializer;
        $this->source = $source;
        $this->eventBusName = $eventBusName;
        $this->scheduler = $scheduler;
        $this->scheduleGroup = $scheduleGroup;
        $this->defaultTargetArn = $defaultTargetArn;
        $this->defaultRoleArn = $defaultRoleArn;
    }

    public function send(Envelope $envelope): Envelope
    {
        /** @var SchedulerStamp|null $schedulerStamp */
        $schedulerStamp = $envelope->last(SchedulerStamp::class);

        if ($schedulerStamp !== null) {
            return $this->sendViaScheduler($envelope, $schedulerStamp);
        }

        return $this->sendViaEventBridge($envelope);
    }

    private function sendViaScheduler(Envelope $envelope, SchedulerStamp $stamp): Envelope
    {
        if ($this->scheduler === null) {
            throw new \LogicException('To use EventBridge Scheduler, install async-aws/scheduler: composer require async-aws/scheduler');
        }

        $targetArn = $stamp->getTargetArn() ?? $this->defaultTargetArn;
        $roleArn = $stamp->getRoleArn() ?? $this->defaultRoleArn;

        if ($targetArn === null || $roleArn === null) {
            throw new \InvalidArgumentException('A target ARN and role ARN are required for scheduling. Provide them via SchedulerStamp or DSN parameters (target_arn, role_arn).');
        }

        $encodedMessage = $this->serializer->encode($envelope);
        $scheduleName = 'bref-msg-' . bin2hex(random_bytes(12));
        $expression = $stamp->getScheduleExpression();

        $input = [
            'Name' => $scheduleName,
            'ScheduleExpression' => $expression,
            'FlexibleTimeWindow' => new FlexibleTimeWindow(['Mode' => FlexibleTimeWindowMode::OFF]),
            'ActionAfterCompletion' => str_starts_with($expression, 'at(') ? ActionAfterCompletion::DELETE : ActionAfterCompletion::NONE,
            'Target' => new Target([
                'Arn' => $targetArn,
                'RoleArn' => $roleArn,
                'Input' => json_encode($encodedMessage, JSON_THROW_ON_ERROR),
            ]),
        ];

        if ($stamp->getTimezone() !== null) {
            $input['ScheduleExpressionTimezone'] = $stamp->getTimezone();
        }

        if ($this->scheduleGroup !== null) {
            $input['GroupName'] = $this->scheduleGroup;
        }

        if ($stamp->getDescription() !== null) {
            $input['Description'] = $stamp->getDescription();
        }

        try {
            $result = $this->scheduler->createSchedule($input);
            $scheduleArn = $result->getScheduleArn();
        } catch (Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        return $envelope->with(new ScheduledStamp($scheduleArn, $scheduleName));
    }

    private function sendViaEventBridge(Envelope $envelope): Envelope
    {
        $encodedMessage = $this->serializer->encode($envelope);
        $arguments = [
            'Entries' => [
                [
                    'Detail' => json_encode($encodedMessage, JSON_THROW_ON_ERROR),
                    'DetailType' => 'Symfony Messenger message',
                    'Source' => $this->source,
                ],
            ],
        ];

        if ($this->eventBusName) {
            $arguments['Entries'][0]['EventBusName'] = $this->eventBusName;
        }

        try {
            $result = $this->eventBridge->putEvents($arguments);
            $failedCount = $result->getFailedEntryCount();
        } catch (Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if ($failedCount > 0) {
            foreach ($result->getEntries() as $entry) {
                $reason = $entry->getErrorMessage() ?? 'no reason provided';
                throw new TransportException("$failedCount message(s) could not be published to EventBridge: $reason.");
            }
        }

        return $envelope;
    }

    public function get(): iterable
    {
        throw new Exception('Not implemented');
    }

    public function ack(Envelope $envelope): void
    {
        throw new Exception('Not implemented');
    }

    public function reject(Envelope $envelope): void
    {
        throw new Exception('Not implemented');
    }
}
