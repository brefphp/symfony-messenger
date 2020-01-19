<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Unit\Service;

use Bref\Symfony\Messenger\Service\TypeProvider;
use PHPUnit\Framework\TestCase;

class TypeProviderTest extends TestCase
{
    /**
     * @dataProvider getLambdaEvents
     */
    public function testGetType(string $expected, array $input)
    {
        $provider = new TypeProvider([]);
        $output = $provider->getType($input);

        $this->assertEquals($expected, $output);
    }

    public function getLambdaEvents()
    {
        $basePath = dirname(__DIR__, 2) . '/Resources/LambdaEvent/';

        yield ['s3', json_decode(file_get_contents($basePath . 's3.json'), true)];
        yield ['sns', json_decode(file_get_contents($basePath . 'sns.json'), true)];
        yield ['sqs', json_decode(file_get_contents($basePath . 'sqs.json'), true)];
        yield ['sqs', json_decode(file_get_contents($basePath . 'sqs_fifo.json'), true)];
    }
}
