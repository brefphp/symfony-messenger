<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Unit\DependencyInjection;

use Bref\Symfony\Messenger\DependencyInjection\BrefMessengerExtension;
use Bref\Symfony\Messenger\Service\SimpleBusDriver;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BrefMessengerExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [new BrefMessengerExtension];
    }

    public function testNoConfigIsValid()
    {
        $this->load();

        $this->assertContainerBuilderHasService(SimpleBusDriver::class);
    }

    /**
     * @dataProvider providePrependSetsMessengerTransportsParameterCases
     */
    public function testPrependSetsMessengerTransportsParameter(
        array $existConfig,
        array $expectedTransportsParameter,
    ): void {
        $container = self::createMock(ContainerBuilder::class);
        $container->method('getExtensionConfig')
            ->with('framework')
            ->willReturn($existConfig);

        $container->expects(self::atMost(2))
            ->method('setParameter')
            ->with(
                'messenger.transports',
                $expectedTransportsParameter
            );

        $extension = new BrefMessengerExtension;
        $extension->prepend($container);
    }

    public function providePrependSetsMessengerTransportsParameterCases(): iterable
    {
        yield 'single messenger config' => [
            'existConfig' => [
                [
                    'messenger' => [
                        'transports' => [
                            'async' => 'async://',
                        ],
                    ],
                ],
            ],
            'expectedTransportsParameter' => [
                'async' => 'async://',
            ],
        ];

        yield 'multiple messenger configs' => [
            'existConfig' => [
                [
                    'messenger' => [
                        'transports' => [
                            'async' => 'async://',
                        ],
                    ],
                ],
                [
                    'messenger' => [
                        'transports' => [
                            'sync' => 'sync://',
                        ],
                    ],
                ],
            ],
            'expectedTransportsParameter' => [
                'async' => 'async://',
                'sync' => 'sync://',
            ],
        ];

        yield 'multiple messenger configs with same transport' => [
            'existConfig' => [
                [
                    'messenger' => [
                        'transports' => [
                            'async' => 'async://',
                        ],
                    ],
                ],
                [
                    'messenger' => [
                        'transports' => [
                            'async' => 'async_overridden://',
                        ],
                    ],
                ],
            ],
            'expectedTransportsParameter' => [
                'async' => 'async_overridden://',
            ],
        ];

        yield 'multiple messenger configs with different transports' => [
            'existConfig' => [
                [
                    'messenger' => [
                        'transports' => [
                            'async' => 'async://',
                        ],
                    ],
                ],
                [
                    'messenger' => [
                        'transports' => [
                            'sync' => 'sync://',
                        ],
                    ],
                ],
                [
                    'messenger' => [
                        'transports' => [
                            'async' => 'async_overridden://',
                        ],
                    ],
                ],
            ],
            'expectedTransportsParameter' => [
                'async' => 'async_overridden://',
                'sync' => 'sync://',
            ],
        ];

        yield 'multiple messenger configs with different transports order' => [
            'existConfig' => [
                [
                    'messenger' => [
                        'transports' => [
                            'sync' => 'sync://',
                        ],
                    ],
                ],
                [
                    'messenger' => [
                        'transports' => [
                            'async' => [
                                'dsn' => 'async://',
                            ],
                        ],
                    ],
                ],
                [
                    'messenger' => [
                        'transports' => [
                            'async' => [
                                'dsn' => 'async_overridden://',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedTransportsParameter' => [
                'sync' => 'sync://',
                'async' => [
                    'dsn' => 'async_overridden://',
                ],
            ],
        ];

        yield 'multiple messenger configs with different transports order and extra keys' => [
            'existConfig' => [
                [
                    'messenger' => [
                        'transports' => [
                            'sync' => 'sync://',
                        ],
                    ],
                ],
                [
                    'messenger' => [
                        'transports' => [
                            'async' => [
                                'dsn' => 'async://',
                                'options' => [
                                    'queue' => 'queue',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'messenger' => [
                        'transports' => [
                            'async' => [
                                'dsn' => 'async_overridden://',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedTransportsParameter' => [
                'sync' => 'sync://',
                'async' => [
                    'dsn' => 'async_overridden://',
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideDoesNotSetMessengerTransportsParameterCases
     */
    public function testPrependDoesNotSetMessengerTransportsParameterWhenNoMessengerConfigExists(
        array $config,
    ): void {
        $container = self::createMock(ContainerBuilder::class);
        $container->method('getExtensionConfig')
            ->with('framework')
            ->willReturn($config);

        $container->expects(self::never())->method('setParameter');

        $extension = new BrefMessengerExtension;
        $extension->prepend($container);
    }

    public function provideDoesNotSetMessengerTransportsParameterCases(): iterable
    {
        yield 'empty config' => [
            'config' => [],
        ];

        yield 'empty messenger config' => [
            'config' => [
                'messenger' => [],
            ],
        ];

        yield 'not empty messenger config without transports key' => [
            'config' => [
                'messenger' => [
                    'busses' => [],
                ],
            ],
        ];

        yield 'not empty messenger config with empty transports key' => [
            'config' => [
                'messenger' => [
                    'transports' => [],
                    'busses' => [],
                ],
            ],
        ];
    }
}
