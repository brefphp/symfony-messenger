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

        $container->expects(self::once())
            ->method('setParameter')
            ->with(
                'messenger.transports',
                $expectedTransportsParameter
            );

        $extension = new BrefMessengerExtension;
        $extension->prepend($container);
    }

    public static function providePrependSetsMessengerTransportsParameterCases(): iterable
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

        yield 'empty transports config when messenger only consuming messages' => [
            'existConfig' => [
                [
                    'messenger' => [
                        'transports' => [],
                    ],
                ],
            ],
            'expectedTransportsParameter' => [],
        ];

        yield 'multiple messenger configs with empty transports key when messenger only consuming messages' => [
            'existConfig' => [
                [
                    'messenger' => [
                        'transports' => [],
                        'busses' => [],
                    ],
                ],
            ],
            'expectedTransportsParameter' => [],
        ];
    }
}
