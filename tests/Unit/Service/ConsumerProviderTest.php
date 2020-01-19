<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Unit\Service;

use Bref\Symfony\Messenger\Exception\ConsumerNotFoundException;
use Bref\Symfony\Messenger\Service\Consumer;
use Bref\Symfony\Messenger\Service\ConsumerProvider;
use PHPUnit\Framework\TestCase;

class ConsumerProviderTest extends TestCase
{
    public function testConsume()
    {
        $event = ['foobar' => '4711'];
        $input = [];
        $consumer = new class($input) implements Consumer {
            private $event;

            public function __construct(array &$event)
            {
                $this->event = &$event;
            }

            public function consume(string $type, array $event): void
            {
                $this->event = $event;
            }

            public static function supportedTypes(): array
            {
                return [];
            }
        };

        $provider = new ConsumerProvider(['biz' => $consumer]);
        $provider->consume('biz', $event);

        $this->assertEquals($event, $input);
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

        $this->expectException(ConsumerNotFoundException::class);
        $provider->consume('bar', []);
    }
}
