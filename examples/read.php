<?php
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';

$client = new Drebedengi\Client(Drebedengi\Credentials::fromEnvironment());
echo json_encode($client->getBalance(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
