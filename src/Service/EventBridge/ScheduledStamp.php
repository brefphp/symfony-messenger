<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\EventBridge;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

class ScheduledStamp implements NonSendableStampInterface
{
    private string $scheduleArn;
    private string $scheduleName;

    public function __construct(string $scheduleArn, string $scheduleName)
    {
        $this->scheduleArn = $scheduleArn;
        $this->scheduleName = $scheduleName;
    }

    public function getScheduleArn(): string
    {
        return $this->scheduleArn;
    }

    public function getScheduleName(): string
    {
        return $this->scheduleName;
    }
}
