<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\DependencyInjection;

use AsyncAws\EventBridge\EventBridgeClient;
use AsyncAws\Sns\SnsClient;
use AsyncAws\Sqs\SqsClient;
use Bref\Symfony\Messenger\Service\EventBridge\EventBridgeTransportFactory;
use Bref\Symfony\Messenger\Service\Sns\SnsTransportFactory;
use Bref\Symfony\Messenger\Service\Sqs\SqsTransportFactory;

class TransportProvider
{
    public static function getAllServices(): array
    {
        return [
            'sns' => [
                'default_client' => SnsClient::class,
                'package' => 'async-aws/sns',
                'transport_factory' => SnsTransportFactory::class,
            ],
            'sqs' => [
                'default_client' => SqsClient::class,
                'package' => 'async-aws/sqs',
                'transport_factory' => SqsTransportFactory::class,
            ],
            'event_bridge' => [
                'default_client' => EventBridgeClient::class,
                'package' => 'async-aws/event-bridge',
                'transport_factory' => EventBridgeTransportFactory::class,
            ],
        ];
    }

    public static function getServiceNames(): array
    {
        $services = self::getAllServices();

        return array_keys($services);
    }
}
