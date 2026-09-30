#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/autoload.php';

use Drebedengi\Client;
use Drebedengi\Credentials;

try {
    $args = array_slice($argv, 1);
    $demo = in_array('--demo', $args, true);
    $write = in_array('--write', $args, true);
    $args = array_values(array_filter($args, static fn($v) => !in_array($v, ['--demo', '--write'], true)));
    $command = $args[0] ?? 'help';
    if ($command === 'help' || $command === '--help') {
        echo "Usage: php bin/drebedengi.php [--demo] COMMAND [JSON]\n";
        echo "Commands: methods, balance, records, currencies, categories, places, sources, tags, revision\n";
        echo "Raw: call METHOD '[arg1,arg2]' [--write]\n";
        echo "JSON may be @file.json. Credentials from project .env or environment: DREB_API_ID, DREB_LOGIN, DREB_PASSWORD.\n";
        exit(0);
    }
    if ($command === 'methods') {
        echo json_encode(Client::operations(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        exit(0);
    }
    $aliases = ['balance' => 'getBalance', 'records' => 'getRecordList',
        'currencies' => 'getCurrencyList', 'categories' => 'getCategoryList',
        'places' => 'getPlaceList', 'sources' => 'getSourceList',
        'tags' => 'getTagList', 'revision' => 'getCurrentRevision'];
    $method = $command === 'call' ? ($args[1] ?? '') : ($aliases[$command] ?? '');
    if (!array_key_exists($method, Client::operations())) {
        throw new InvalidArgumentException('Unknown command or method. Run help or methods.');
    }
    if (!str_starts_with($method, 'get') && !$write) {
        throw new InvalidArgumentException('Mutating methods require --write.');
    }
    if ($demo && !str_starts_with($method, 'get')) {
        throw new InvalidArgumentException('Demo mode supports read operations only.');
    }
    $json = $args[$command === 'call' ? 2 : 1] ?? '[]';
    if (str_starts_with($json, '@')) {
        $json = file_get_contents(substr($json, 1));
        if ($json === false) {
            throw new InvalidArgumentException('Cannot read JSON file.');
        }
    }
    $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
    if (!is_array($data)) {
        throw new InvalidArgumentException('JSON must contain an object or array.');
    }
    $client = new Client($demo ? Credentials::demo() : Credentials::fromEnvironment());
    $result = $command === 'call'
        ? $client->call($method, $data)
        : ($method === 'getCurrentRevision' ? $client->$method() : $client->$method($data));
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $e) {
    // Never print stack traces: SOAP arguments contain credentials.
    fwrite(STDERR, json_encode(['error' => $e instanceof InvalidArgumentException || $e instanceof RuntimeException
        ? $e->getMessage() : 'Command failed. Check JSON, PHP extensions and network.'],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
    exit(1);
}
