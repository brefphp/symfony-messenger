<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Unit\Service\Sqs;

use AsyncAws\Sqs\Result\SendMessageResult;
use AsyncAws\Sqs\SqsClient;
use AsyncAws\Sqs\ValueObject\MessageAttributeValue;
use Bref\Symfony\Messenger\Service\Sqs\SqsTransport;
use Bref\Symfony\Messenger\Test\Functional\BaseFunctionalTest;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class SqsTransportTest extends BaseFunctionalTest
{
    /** @var SqsClient|MockObject  */
    protected $sqsClient;
    /** @var SendMessageResult|MockObject  */
    protected $result;
    /** @var MockObject|PhpSerializer  */
    protected $serializer;
    /** @var SqsTransport  */
    protected $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sqsClient = $this->getMockBuilder(SqsClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->result = $this->getMockBuilder(SendMessageResult::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->result->method('getMessageId')
            ->willReturn('the_message_id');

        $this->serializer = $this->getMockBuilder(PhpSerializer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->serializer->method('encode')
            ->willReturn([
                'body' => 'the_body',
                'headers' => [
                    'a' => 'header',
                ],
            ]);

        $this->transport = new SqsTransport(
            $this->sqsClient,
            $this->serializer,
            'the_queue',
            'the_group_id'
        );
    }

    public function test send(): void
    {
        $this->sqsClient->expects($this->once())
            ->method('sendMessage')
            ->with($this->callback(function ($arg) {
                return is_array($arg)
                    && $arg['MessageAttributes']['Headers'] instanceof MessageAttributeValue
                    && $arg['MessageAttributes']['Headers']->getStringValue() === '{"a":"header"}'
                    && $arg['MessageBody'] === 'the_body'
                    && $arg['QueueUrl'] === 'the_queue'
                    && $arg['MessageGroupId'] === 'the_group_id'
                    && $arg['DelaySeconds'] === 0;
            }))
            ->willReturn($this->result);

        $returnEnvelope = $this->transport->send(new Envelope((object) [
            'body' => 'the_message',
        ]));

        /** @var TransportMessageIdStamp $stamp */
        $stamp = $returnEnvelope->last(TransportMessageIdStamp::class);
        $this->assertEquals('the_message_id', $stamp->getId());
    }

    public function test send with stamps(): void
    {
        $this->sqsClient->expects($this->once())
            ->method('sendMessage')
            ->with($this->callback(function ($arg) {
                return is_array($arg)
                    && $arg['MessageAttributes']['Headers'] instanceof MessageAttributeValue
                    && $arg['MessageAttributes']['Headers']->getStringValue() === '{"a":"header"}'
                    && $arg['MessageBody'] === 'the_body'
                    && $arg['QueueUrl'] === 'the_queue'
                    && $arg['MessageGroupId'] === 'the_group_id'
                    && $arg['DelaySeconds'] === 63
                    && $arg['MessageDeduplicationId'] === 'the_deduplication_id';
            }))
            ->willReturn($this->result);

        $returnEnvelope = $this->transport->send(new Envelope((object) [
            'body' => 'the_message',
        ], [
            new TransportMessageIdStamp('the_deduplication_id'),
            new DelayStamp(63000),
        ]));

        /** @var TransportMessageIdStamp $stamp */
        $stamp = $returnEnvelope->last(TransportMessageIdStamp::class);
        $this->assertEquals('the_message_id', $stamp->getId());
    }
}
