<?php
declare(strict_types=1);
namespace Drebedengi;

/** Thin SOAP client: retains server field names, integer hundredths and response types. */
final class Client
{
    private \Closure $transport;

    public function __construct(
        private readonly Credentials $credentials,
        ?callable $transport = null,
    ) {
        if ($transport !== null) {
            $this->transport = \Closure::fromCallable($transport);
            return;
        }
        if (!extension_loaded('soap') || !extension_loaded('openssl')) {
            throw new \RuntimeException('Enable PHP extensions soap and openssl.');
        }
        $soap = new \SoapClient(__DIR__ . '/../resources/dd.wsdl', [
            'location' => 'https://www.drebedengi.ru/soap/',
            'exceptions' => true,
            'trace' => false,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'connection_timeout' => 10,
            'stream_context' => stream_context_create([
                'http' => ['timeout' => 30, 'follow_location' => 0],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]),
        ]);
        $this->transport = static fn(string $method, array $args): mixed => $soap->__soapCall($method, $args);
    }

    /** Authenticated operations and ordered parameters, from the bundled WSDL. */
    public static function operations(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/../resources/operations.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Arguments exclude credentials and must be a positional list. No automatic retries. */
    public function call(string $method, array $arguments = []): mixed
    {
        $operations = self::operations();
        if (!isset($operations[$method])) {
            throw new \InvalidArgumentException('Unknown authenticated API method.');
        }
        if (!array_is_list($arguments) || count($arguments) !== count($operations[$method])) {
            throw new \InvalidArgumentException('Expected arguments: ' . implode(', ', $operations[$method]));
        }
        try {
            return ($this->transport)($method, [...$this->credentials->arguments(), ...$arguments]);
        } catch (\SoapFault $e) {
            // Do not propagate response XML, credentials or untrusted fault text to logs.
            throw new \RuntimeException('SOAP call failed: ' . $method . '. Check access, credentials and connectivity.');
        }
    }

    public function getCurrencyList(array $ids = []): mixed { return $this->call(__FUNCTION__, [$ids]); }
    public function getCategoryList(array $ids = []): mixed { return $this->call(__FUNCTION__, [$ids]); }
    public function getSourceList(array $ids = []): mixed { return $this->call(__FUNCTION__, [$ids]); }
    public function getPlaceList(array $ids = []): mixed { return $this->call(__FUNCTION__, [$ids]); }
    public function getTagList(array $ids = []): mixed { return $this->call(__FUNCTION__, [$ids]); }
    public function getBalance(array $params = []): mixed { return $this->call(__FUNCTION__, [$params]); }
    public function getCurrentRevision(): mixed { return $this->call(__FUNCTION__); }
    public function getChangeList(string $revision): mixed { return $this->call(__FUNCTION__, [$revision]); }

    /** Default: last 20 records per operation type, NOT complete account history. */
    public function getRecordList(array $params = [], array $ids = []): mixed
    {
        return $this->call(__FUNCTION__, [array_replace([
            'is_report' => false, 'is_show_duty' => true,
            'r_period' => 8, 'r_how' => 1, 'r_what' => 6,
            'r_currency' => 0, 'r_is_place' => 0, 'r_is_tag' => 0,
        ], $params), $ids]);
    }

    /** Sum is integer hundredths. Preserve returned client/server IDs for updates. */
    public function setRecordList(array $records): mixed
    {
        if (!array_is_list($records) || $records === []) {
            throw new \InvalidArgumentException('Records must be a non-empty positional list.');
        }
        return $this->call(__FUNCTION__, [$records]);
    }

    public function deleteObject(int $id, string $type): mixed
    {
        if ($id <= 0 || !in_array($type, ['waste', 'income', 'move', 'change', 'object', 'currency', 'tag', 'accum'], true)) {
            throw new \InvalidArgumentException('Invalid object ID or type.');
        }
        return $this->call(__FUNCTION__, [$id, $type]);
    }
}
