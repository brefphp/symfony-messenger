<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Functional\Service\Sns;

use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\Sns\Result\PublishResponse;
use AsyncAws\Sns\SnsClient;
use Bref\Symfony\Messenger\Service\Sns\SnsFifoStamp;
use Bref\Symfony\Messenger\Service\Sns\SnsTransport;
use Bref\Symfony\Messenger\Service\Sns\SnsTransportFactory;
use Bref\Symfony\Messenger\Test\Functional\BaseFunctionalTest;
use Bref\Symfony\Messenger\Test\Resources\TestMessage\TestMessage;
use Nyholm\BundleTest\TestKernel;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class SnsTransportTest extends BaseFunctionalTest
{
    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel([
            'config' => static function (TestKernel $kernel) {
                $kernel->addTestConfig(dirname(__DIR__, 3).'/Resources/config/sns.yaml');
            },
        ]);
    }

    public function test factory(): void
    {
        /** @var SnsTransportFactory $factory */
        $factory = self::getContainer()->get(SnsTransportFactory::class);
        $this->assertInstanceOf(SnsTransportFactory::class, $factory);

        $this->assertTrue($factory->supports('sns://arn:aws:sns:us-east-1:1234567890:test', []));
        $this->assertFalse($factory->supports('https://example.com', []));
        $this->assertFalse($factory->supports('arn:aws:sns:us-east-1:1234567890:test', []));

        $transport = $factory->createTransport('sns://arn:aws:sns:us-east-1:1234567890:test', [], new PhpSerializer);
        $this->assertInstanceOf(SnsTransport::class, $transport);
    }

    public function test send message(): void
    {
        $sns = $this->getMockBuilder(SnsClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['publish'])
            ->getMock();
        $sns->expects($this->once())
            ->method('publish')
            ->with($this->callback(function ($input) {
                $this->assertEquals('arn:aws:sns:us-east-1:1234567890:test', $input['TopicArn']);

                return true;
            }))
            ->willReturn(ResultMockFactory::create(PublishResponse::class, ['MessageId' => 4711]));
        self::getContainer()->set('bref.messenger.sns_client', $sns);

        /** @var MessageBusInterface $bus */
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new TestMessage('hello'));
    }

    public function testRejectsMessageWhenQueueIsFifoWithoutStamp()
    {
        $snsClient = $this->getMockBuilder(SnsClient::class)->disableOriginalConstructor()->getMock();
        $serializer = self::getContainer()->get(SerializerInterface::class);
        $snsTransport = new SnsTransport($snsClient, $serializer, "arn:aws:sns:us-east-1:1234567890:test.fifo'"); // fifo suffix designates fifo queue
        $msg = new TestMessage("hello");
        $envelope = new Envelope($msg);
        $this->expectExceptionMessage("SnsFifoStamp required for fifo topic");
        $snsTransport->send($envelope);
    }
    public function testAcceptsMessageWhenQueueIsFifoWithStamp(){
        $snsClient = $this->getMockBuilder(SnsClient::class)->disableOriginalConstructor()->getMock();
        $snsClient->expects($this->once())->method("publish")->willReturn(ResultMockFactory::create(PublishResponse::class, ['MessageId' => 4711]));
        $serializer = self::getContainer()->get(SerializerInterface::class);
        $snsTransport = new SnsTransport($snsClient, $serializer, "arn:aws:sns:us-east-1:1234567890:test.fifo'"); // fifo suffix designates fifo queue
        $msg = new TestMessage("hello");
        $envelope = new Envelope($msg, [new SnsFifoStamp("123","456")]);
        $resp = $snsTransport->send($envelope);
        $this->assertInstanceOf(Envelope::class, $resp);
    }
    public function testAttachingSnsFifoStampToMessageAppliesMessageGroupId(){
        $snsClient = $this->getMockBuilder(SnsClient::class)->disableOriginalConstructor()->getMock();
        $snsClient->expects($this->once())->method("publish")
            ->with($this->callback(function($params){
                $this->assertEquals("123", $params["MessageGroupId"]);
                return true;
            }))
            ->willReturn(ResultMockFactory::create(PublishResponse::class, ['MessageId' => 4711]));
        $serializer = self::getContainer()->get(SerializerInterface::class);
        $snsTransport = new SnsTransport($snsClient, $serializer, "arn:aws:sns:us-east-1:1234567890:test.fifo'"); // fifo suffix designates fifo queue
        $msg = new TestMessage("hello");
        $envelope = new Envelope($msg, [new SnsFifoStamp("123","456")]);
        $resp = $snsTransport->send($envelope);
        $this->assertInstanceOf(Envelope::class, $resp);
    }
    public function testAttachingSnsFifoStampToMessageAppliesMessageDeDeuplicatId(){
        $snsClient = $this->getMockBuilder(SnsClient::class)->disableOriginalConstructor()->getMock();
        $snsClient->expects($this->once())->method("publish")
            ->with($this->callback(function($params){
                $this->assertEquals("456", $params["MessageDeduplicationId"]);
                return true;
            }))
            ->willReturn(ResultMockFactory::create(PublishResponse::class, ['MessageId' => 4711]));
        $serializer = self::getContainer()->get(SerializerInterface::class);
        $snsTransport = new SnsTransport($snsClient, $serializer, "arn:aws:sns:us-east-1:1234567890:test.fifo'"); // fifo suffix designates fifo queue
        $msg = new TestMessage("hello");
        $envelope = new Envelope($msg, [new SnsFifoStamp("123","456")]);
        $resp = $snsTransport->send($envelope);
        $this->assertInstanceOf(Envelope::class, $resp);
    }
    public function testAttachingSnsFifoStampToMessageAllowsNullMessageGroupId(){
        //  in fifo queues message group id can be null when the de-dupe scope is the entire queue.
        $snsClient = $this->getMockBuilder(SnsClient::class)->disableOriginalConstructor()->getMock();
        $snsClient->expects($this->once())->method("publish")
            ->willReturn(ResultMockFactory::create(PublishResponse::class, ['MessageId' => 4711]));
        $serializer = self::getContainer()->get(SerializerInterface::class);
        $snsTransport = new SnsTransport($snsClient, $serializer, "arn:aws:sns:us-east-1:1234567890:test.fifo'"); // fifo suffix designates fifo queue
        $msg = new TestMessage("hello");
        $envelope = new Envelope($msg, [new SnsFifoStamp(null,"456")]);
        $resp = $snsTransport->send($envelope);
        $this->assertInstanceOf(Envelope::class, $resp);
    }
}
