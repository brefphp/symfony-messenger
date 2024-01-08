<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Functional;

use Bref\Symfony\Messenger\BrefMessengerBundle;
use Nyholm\BundleTest\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class BaseFunctionalTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        /**
         * @var TestKernel $kernel
         */
        $kernel = parent::createKernel($options);
        $kernel->addTestBundle(BrefMessengerBundle::class);
        $kernel->handleOptions($options);

        return $kernel;
    }
}
