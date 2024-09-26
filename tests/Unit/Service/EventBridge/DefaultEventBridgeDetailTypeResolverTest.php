<?php

namespace Bref\Symfony\Messenger\Test\Unit\Service\EventBridge;

use Bref\Symfony\Messenger\Service\EventBridge\DefaultEventBridgeDetailTypeResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;

class DefaultEventBridgeDetailTypeResolverTest extends TestCase
{
    private DefaultEventBridgeDetailTypeResolver $resolver;

    public function setUp(): void
    {
        $this->resolver = new DefaultEventBridgeDetailTypeResolver();
    }

    public function testResolver(): void
    {
        $envelope = new Envelope(new \stdClass());

        $this->assertEquals('stdClass', $this->resolver->resolveDetailType($envelope));
    }
}
