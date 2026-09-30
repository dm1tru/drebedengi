<?php
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
use Drebedengi\Credentials;
use Drebedengi\EnvFile;

function verify(bool $ok): void {
    if (!$ok) { throw new RuntimeException('Env test failed'); }
}
$path = tempnam(sys_get_temp_dir(), 'dreb-env-');
$keys = ['DREB_API_ID', 'DREB_LOGIN', 'DREB_PASSWORD'];
$saved = [];
foreach ($keys as $key) { $saved[$key] = getenv($key); putenv($key); }
try {
    file_put_contents($path, "\xEF\xBB\xBF" . <<<'ENV'
# Test credentials only
export DREB_API_ID = test-id
DREB_LOGIN = "test@example.com" # comment
DREB_PASSWORD = ' p#=$word\path ' # literal password
ENV);
    verify(Credentials::fromEnvironment($path)->arguments() === ['test-id', 'test@example.com', ' p#=$word\path ']);
    verify(getenv('DREB_PASSWORD') === false);
    putenv('DREB_API_ID=override');
    verify(Credentials::fromEnvironment($path)->arguments()[0] === 'override');
    putenv('DREB_API_ID=');
    try {
        Credentials::fromEnvironment($path);
        throw new RuntimeException('Expected empty environment rejection');
    } catch (InvalidArgumentException $e) {}
    putenv('DREB_API_ID');
    file_put_contents($path, "DREB_API_ID=a\r\nDREB_LOGIN=b\r\nDREB_PASSWORD=0\r\n");
    verify(Credentials::fromEnvironment($path)->arguments()[2] === '0');
    file_put_contents($path, "DREB_PASSWORD=\"test-secret\n");
    try {
        EnvFile::read($path);
        throw new RuntimeException('Expected syntax rejection');
    } catch (InvalidArgumentException $e) {
        verify(!str_contains($e->getMessage(), 'test-secret'));
    }
    verify(EnvFile::read($path . '-missing') === []);
    echo "OK: .env loading, literal values, precedence, empty values, errors\n";
} finally {
    unlink($path);
    foreach ($saved as $key => $value) {
        putenv($value === false ? $key : $key . '=' . $value);
    }
}