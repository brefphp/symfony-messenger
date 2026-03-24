<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\EventBridge;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

class SchedulerStamp implements NonSendableStampInterface
{
    private string $scheduleExpression;
    private ?string $targetArn;
    private ?string $roleArn;
    private ?string $timezone;
    private ?string $description;

    public function __construct(
        string $scheduleExpression,
        ?string $targetArn = null,
        ?string $roleArn = null,
        ?string $timezone = null,
        ?string $description = null
    ) {
        $this->scheduleExpression = $scheduleExpression;
        $this->targetArn = $targetArn;
        $this->roleArn = $roleArn;
        $this->timezone = $timezone;
        $this->description = $description;
    }

    public static function oneTime(\DateTimeImmutable $at, string $timezone = 'UTC'): self
    {
        return new self(
            sprintf('at(%s)', $at->format('Y-m-d\TH:i:s')),
            null,
            null,
            $timezone
        );
    }

    public function getScheduleExpression(): string
    {
        return $this->scheduleExpression;
    }

    public function getTargetArn(): ?string
    {
        return $this->targetArn;
    }

    public function getRoleArn(): ?string
    {
        return $this->roleArn;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
