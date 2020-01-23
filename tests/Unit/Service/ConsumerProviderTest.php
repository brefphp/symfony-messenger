<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Unit\Service;

use Bref\Symfony\Messenger\Exception\ConsumerNotFound;
use Bref\Symfony\Messenger\Service\Consumer;
use Bref\Symfony\Messenger\Service\ConsumerProvider;
use PHPUnit\Framework\TestCase;

class ConsumerProviderTest extends TestCase
{
    public function testConsume()
    {
        $event = ['foobar' => '4711'];
        $consumer = new class() implements Consumer {
            /** @var array */
            private $event;

            public function consume(string $type, array $event): void
            {
                $this->event = $event;
            }

            public function getEvent(): array
            {
                return $this->event;
            }

            public static function supportedTypes(): array
            {
                return [];
            }
        };

        $provider = new ConsumerProvider(['biz' => $consumer]);
        $provider->consume('biz', $event);

        $this->assertEquals($event, $consumer->getEvent());
    }

    public function testConsumeWithoutConsumer()
    {
        $consumer = new class implements Consumer {
            public function consume(string $type, array $event): void
            {
            }

            public static function supportedTypes(): array
            {
                return [];
            }
        };
        $provider = new ConsumerProvider(['foo' => $consumer]);

        $this->expectException(ConsumerNotFound::class);
        $provider->consume('bar', []);
    }
}
