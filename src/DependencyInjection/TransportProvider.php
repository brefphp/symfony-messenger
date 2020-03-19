<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\DependencyInjection;

use Aws\EventBridge\EventBridgeClient;
use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
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
                'package' => 'aws/aws-sdk-php',
                'transport_factory' => SnsTransportFactory::class,
            ],
            'sqs' => [
                'default_client' => SqsClient::class,
                'package' => 'aws/aws-sdk-php',
                'transport_factory' => SqsTransportFactory::class,
            ],
            'event_bridge' => [
                'default_client' => EventBridgeClient::class,
                'package' => 'aws/aws-sdk-php',
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
