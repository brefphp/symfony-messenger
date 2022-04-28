<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Unit\Service\Kafka;

use Bref\Context\Context;
use Bref\Event\Kafka\KafkaEvent;
use Bref\Symfony\Messenger\Service\BusDriver;
use Bref\Symfony\Messenger\Service\Kafka\KafkaConsumer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class KafkaConsumerTest extends TestCase
{
    public function testPushesRecords()
    {
        $busDriver  = $this->createMock(BusDriver::class);
        $bus        = new MessageBus;
        $serializer = $this->createMock(SerializerInterface::class);
        $transport  = 'async';

        $event = new KafkaEvent(
            [
                'records' => [
                    'mytopic-0' => [
                        [
                            'value'   => 'SGVsbG8sIHRoaXMgaXMgYSB0ZXN0Lg==',
                            'headers' => [
                                [
                                    'type' => [99, 111, 114, 101],
                                ],
                                [
                                    'another_header' => [46, 46, 46],
                                ],
                            ],
                        ],
                    ],
                    'mytopic-1' => [
                        [
                            'value'   => 'YXNkZgo=',
                            'headers' => [
                                [
                                    'a' => [46, 46, 46],
                                ],
                                [
                                    'b' => [99, 111, 114, 101],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $serializer
            ->expects(self::exactly(count($event->getRecords())))
            ->method('decode')
            ->willReturnMap(
                [
                    [
                        ['body' => $event->getRecords()[0]->getValue(), 'headers' => $event->getRecords()[0]->getHeaders()],
                        $envelopeA = new Envelope(new \stdClass),
                    ],
                    [
                        ['body' => $event->getRecords()[1]->getValue(), 'headers' => $event->getRecords()[1]->getHeaders()],
                        $envelopeB = new Envelope(new \stdClass),
                    ],
                ]
            );

        $busDriver
            ->expects(self::exactly(count($event->getRecords())))
            ->method('putEnvelopeOnBus')
            ->withConsecutive(
                [$bus, $envelopeA, $transport],
                [$bus, $envelopeB, $transport],
            )
        ;

        $consumer = new KafkaConsumer($busDriver, $bus, $serializer, $transport);

        $consumer->handleKafka($event, new Context('', 0, '', ''));
    }
}
