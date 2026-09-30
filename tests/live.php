<?php
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
$client = new Drebedengi\Client(Drebedengi\Credentials::demo());
foreach (['getCurrencyList', 'getCategoryList', 'getPlaceList', 'getBalance', 'getRecordList'] as $method) {
    $result = $client->$method();
    if (!is_array($result)) {
        throw new RuntimeException("Unexpected response type: $method");
    }
    echo $method . ': OK (' . count($result) . " rows)\n";
}
