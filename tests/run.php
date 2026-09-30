<?php
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
use Drebedengi\Client;
use Drebedengi\Credentials;

$count = 0;
function check(bool $condition, string $label): void {
    global $count;
    if (!$condition) { throw new RuntimeException("FAIL: $label"); }
    $count++;
}
function rejects(callable $fn, string $class): void {
    try { $fn(); } catch (Throwable $e) {
        check($e instanceof $class, 'expected exception ' . $class);
        return;
    }
    throw new RuntimeException('Expected rejection');
}

$seen = [];
$client = new Client(Credentials::demo(), function ($method, $args) use (&$seen) {
    $seen = [$method, $args];
    return [['id' => '9223372036854775807', 'sum' => '12345']];
});
$result = $client->getRecordList(['r_period' => 0, 'period_from' => '2026-09-01', 'period_to' => '2026-09-30']);
check($seen[0] === 'getRecordList', 'record method');
check(array_slice($seen[1], 0, 3) === ['demo_api', 'demo@example.com', 'demo'], 'credential order');
check($seen[1][3]['r_period'] === 0 && $seen[1][3]['r_what'] === 6 && $seen[1][4] === [], 'filters and ID parameter');
check($result[0]['sum'] === '12345' && $result[0]['id'] === '9223372036854775807', 'preserve numeric strings');
$client->getCurrencyList(['17']);
check($seen[1][3] === ['17'], 'ID filter');
$client->getBalance(['restDate' => '2026-09-01']);
check($seen[1][3]['restDate'] === '2026-09-01', 'balance parameters');
$client->setRecordList([['client_id' => 1, 'sum' => 12345]]);
check($seen[1][3][0]['sum'] === 12345, 'write payload');
$client->deleteObject(123, 'waste');
check(array_slice($seen[1], 3) === [123, 'waste'], 'delete arguments');
rejects(fn() => $client->deleteObject(0, 'waste'), InvalidArgumentException::class);
rejects(fn() => $client->setRecordList([5 => []]), InvalidArgumentException::class);
rejects(fn() => $client->call('getServerSubs', ['url']), InvalidArgumentException::class);
rejects(fn() => $client->call('getBalance'), InvalidArgumentException::class);
rejects(fn() => $client->call('getBalance', ['params' => []]), InvalidArgumentException::class);
rejects(fn() => new Credentials('', 'login', 'pass'), InvalidArgumentException::class);
$failing = new Client(Credentials::demo(), fn() => throw new SoapFault('Server', 'secret-password'));
try { $failing->getBalance(); } catch (RuntimeException $e) {
    check(!str_contains($e->getMessage(), 'secret-password') && $e->getPrevious() === null, 'fault redaction');
}

// Use real SOAP serialization without making any network request.
final class RecordingSoap extends SoapClient {
    public string $request = '';
    public function __doRequest(string $request, string $location, string $action, int $version, bool $oneWay = false): ?string {
        $this->request = $request;
        return '<?xml version="1.0"?><s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><s:Body><m:getRecordListResponse xmlns:m="urn:ddengi"><return xsi:type="xsd:string">ok</return></m:getRecordListResponse></s:Body></s:Envelope>';
    }
}
$soap = new RecordingSoap(dirname(__DIR__) . '/resources/dd.wsdl', ['cache_wsdl' => WSDL_CACHE_NONE]);
$wire = new Client(Credentials::demo(), fn($method, $args) => $soap->__soapCall($method, $args));
check($wire->getRecordList(['r_period' => 6]) === 'ok', 'SOAP response decoding');
$xml = new DOMDocument();
$xml->loadXML($soap->request);
$xpath = new DOMXPath($xml);
$xpath->registerNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');
$parts = $xpath->query('/s:Envelope/s:Body/*/*');
check($parts->length === 5, 'five wire arguments');
check($parts->item(0)->textContent === 'demo_api' && $parts->item(1)->textContent === 'demo@example.com', 'wire credentials');
check($parts->item(3)->localName === 'params' && $parts->item(4)->localName === 'idList', 'wire parameter names');
check(str_contains($soap->request, 'r_period'), 'SOAP map serialization');
$schema = simplexml_load_file(dirname(__DIR__) . '/resources/dd.wsdl');
$schema->registerXPathNamespace('w', 'http://schemas.xmlsoap.org/wsdl/');
foreach (Client::operations() as $method => $params) {
    $parts = $schema->xpath('/w:definitions/w:message[@name="' . $method . '"]/w:part');
    check(array_slice(array_map(fn($part) => (string)$part['name'], $parts), 3) === $params, 'WSDL contract ' . $method);
}
echo "OK: $count checks\n";
