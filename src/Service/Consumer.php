<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Service;

interface Consumer
{
    public function consume(string $type, array $event): void;

    public static function supportedTypes(): array;
}
