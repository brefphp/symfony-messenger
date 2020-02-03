<?php declare(strict_types=1);

use Bref\Symfony\Messenger\Service\Sqs\SqsConsumer;

require dirname(__DIR__) . '/config/bootstrap.php';

$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

return $kernel->getContainer()->get(SqsConsumer::class);
