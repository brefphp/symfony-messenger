<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Functional\Service\EventBridge;

use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\EventBridge\Result\PutEventsResponse;
use AsyncAws\Scheduler\Result\CreateScheduleOutput;
use AsyncAws\Scheduler\SchedulerClient;
use Bref\Symfony\Messenger\Service\EventBridge\EventBridgeTransport;
use Bref\Symfony\Messenger\Service\EventBridge\EventBridgeTransportFactory;
use Bref\Symfony\Messenger\Service\EventBridge\ScheduledStamp;
use Bref\Symfony\Messenger\Service\EventBridge\SchedulerStamp;
use Bref\Symfony\Messenger\Test\Functional\BaseFunctionalTest;
use Bref\Symfony\Messenger\Test\Resources\TestMessage\TestMessage;
use Nyholm\BundleTest\TestKernel;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class EventBridgeSchedulerTransportTest extends BaseFunctionalTest
{
    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel([
            'config' => static function (TestKernel $kernel) {
                $kernel->addTestConfig(dirname(__DIR__, 3) . '/Resources/config/eventbridge-scheduler.yaml');
            },
        ]);
    }

    public function test_factory_with_scheduler_params(): void
    {
        /** @var EventBridgeTransportFactory $factory */
        $factory = self::getContainer()->get(EventBridgeTransportFactory::class);

        $transport = $factory->createTransport(
            'eventbridge://myapp.mycomponent?target_arn=arn:aws:lambda:us-east-1:123:function:f&role_arn=arn:aws:iam::123:role/r&schedule_group=grp',
            [],
            new PhpSerializer,
        );
        $this->assertInstanceOf(EventBridgeTransport::class, $transport);
    }

    public function test_send_with_scheduler_stamp(): void
    {
        $schedulerClient = $this->getMockBuilder(SchedulerClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createSchedule'])
            ->getMock();
        $schedulerClient->expects($this->once())
            ->method('createSchedule')
            ->with($this->callback(function ($input) {
                $this->assertEquals('at(2026-04-01T10:00:00)', $input['ScheduleExpression']);
                $this->assertEquals('Europe/Paris', $input['ScheduleExpressionTimezone']);
                $this->assertEquals('test-group', $input['GroupName']);
                $this->assertEquals('DELETE', $input['ActionAfterCompletion']);

                $target = $input['Target'];
                $this->assertEquals('arn:aws:lambda:us-east-1:123456789:function:my-func', $target->getArn());
                $this->assertEquals('arn:aws:iam::123456789:role/scheduler-role', $target->getRoleArn());

                // Verify the payload contains serialized message
                $payload = json_decode($target->getInput(), true);
                $this->assertArrayHasKey('body', $payload);

                return true;
            }))
            ->willReturn(ResultMockFactory::create(CreateScheduleOutput::class, ['ScheduleArn' => 'arn:aws:scheduler:us-east-1:123:schedule/test-group/bref-msg-test']));
        self::getContainer()->set('bref.messenger.scheduler_client', $schedulerClient);

        $stamp = SchedulerStamp::oneTime(new \DateTimeImmutable('2026-04-01 10:00:00'), 'Europe/Paris');

        /** @var EventBridgeTransportFactory $factory */
        $factory = self::getContainer()->get(EventBridgeTransportFactory::class);
        $transport = $factory->createTransport(
            'eventbridge://myapp.mycomponent?target_arn=arn:aws:lambda:us-east-1:123456789:function:my-func&role_arn=arn:aws:iam::123456789:role/scheduler-role&schedule_group=test-group',
            [],
            new PhpSerializer,
        );

        $envelope = new Envelope(new TestMessage('hello'), [$stamp]);
        $result = $transport->send($envelope);

        /** @var ScheduledStamp|null $scheduledStamp */
        $scheduledStamp = $result->last(ScheduledStamp::class);
        $this->assertNotNull($scheduledStamp);
        $this->assertEquals('arn:aws:scheduler:us-east-1:123:schedule/test-group/bref-msg-test', $scheduledStamp->getScheduleArn());
        $this->assertStringStartsWith('bref-msg-', $scheduledStamp->getScheduleName());
    }

    public function test_send_without_stamp_uses_put_events(): void
    {
        $eventBridgeClient = $this->getMockBuilder(EventBridgeClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['putEvents'])
            ->getMock();
        $eventBridgeClient->expects($this->once())
            ->method('putEvents')
            ->with($this->callback(function ($input) {
                $entry = $input['Entries'][0];
                $this->assertEquals('myapp.mycomponent', $entry['Source']);
                $this->assertEquals('Symfony Messenger message', $entry['DetailType']);

                return true;
            }))
            ->willReturn(ResultMockFactory::create(PutEventsResponse::class, ['FailedEntryCount' => 0]));
        self::getContainer()->set('bref.messenger.eventbridge_client', $eventBridgeClient);

        /** @var EventBridgeTransportFactory $factory */
        $factory = self::getContainer()->get(EventBridgeTransportFactory::class);
        $transport = $factory->createTransport('eventbridge://myapp.mycomponent', [], new PhpSerializer);

        $envelope = new Envelope(new TestMessage('hello'));
        $transport->send($envelope);
    }
}
