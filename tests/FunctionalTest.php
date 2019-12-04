<?php declare(strict_types=1);

namespace Bref\Messenger\Test;

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Bref\Messenger\Sqs\SqsTransport;
use Bref\Messenger\Sqs\SqsTransportFactory;
use Bref\Messenger\Test\TestMessage\TestMessage;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class FunctionalTest extends TestCase
{
    /** @var TestKernel */
    private $kernel;
    /** @var ContainerInterface */
    private $container;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel('test', true);
        $this->kernel->boot();
        $this->container = $this->kernel->getContainer()->get('test.service_container');
    }

    public function test factory(): void
    {
        /** @var SqsTransportFactory $factory */
        $factory = $this->container->get(SqsTransportFactory::class);
        $this->assertInstanceOf(SqsTransportFactory::class, $factory);

        $this->assertTrue($factory->supports('sqs://sqs.us-east-1.amazonaws.com/123456789101/test', []));
        $this->assertFalse($factory->supports('https://sqs.us-east-1.amazonaws.com/123456789101/test', []));

        $transport = $factory->createTransport('sqs://sqs.us-east-1.amazonaws.com/123456789101/test', [], new PhpSerializer);
        $this->assertInstanceOf(SqsTransport::class, $transport);
    }

    public function test send message(): void
    {
        /** @var MockHandler $mock */
        $mock = $this->container->get('mock_handler');
        $queueUrl = '';
        $mock->append(function (CommandInterface $cmd, RequestInterface $request) use (&$queueUrl) {
            $queueUrl = (string) $request->getUri();
            return new Result(['MessageId' => 'abcd']);
        });

        /** @var MessageBusInterface $bus */
        $bus = $this->container->get(MessageBusInterface::class);
        $bus->dispatch(new TestMessage('hello'));

        // Check that the URL has been transformed in a HTTPS URL
        $this->assertEquals('https://sqs.us-east-1.amazonaws.com/123456789101/bref-test', $queueUrl);
    }
}
