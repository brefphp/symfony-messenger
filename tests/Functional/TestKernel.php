<?php declare(strict_types=1);

namespace Bref\Messenger\Test\Functional;

use Bref\Messenger\BrefMessengerBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
    private $configFile;

    public function __construct(string $configFile)
    {
        $this->configFile = $configFile;
        parent::__construct('test', true);
    }

    public function registerBundles(): array
    {
        return [
            new FrameworkBundle,
            new BrefMessengerBundle,
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(dirname(__DIR__) . '/Resources/config/default.yaml');
        $loader->load(dirname(__DIR__) . '/Resources/config/' . $this->configFile);
    }
}
