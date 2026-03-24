<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service\EventBridge;

use AsyncAws\Scheduler\SchedulerClient;

final class ScheduleDeleter
{
    /** @var SchedulerClient */
    private $scheduler;

    public function __construct(SchedulerClient $scheduler)
    {
        $this->scheduler = $scheduler;
    }

    public function delete(string $scheduleName, ?string $groupName = null): void
    {
        $input = [
            'Name' => $scheduleName,
            'ClientToken' => bin2hex(random_bytes(16)),
        ];

        if ($groupName !== null) {
            $input['GroupName'] = $groupName;
        }

        $this->scheduler->deleteSchedule($input);
    }
}
