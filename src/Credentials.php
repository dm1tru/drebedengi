<?php
declare(strict_types=1);
namespace Drebedengi;

final class Credentials
{
    public function __construct(
        #[\SensitiveParameter] private readonly string $apiId,
        #[\SensitiveParameter] private readonly string $login,
        #[\SensitiveParameter] private readonly string $password,
    ) {
        if ($apiId === '' || $login === '' || $password === '') {
            throw new \InvalidArgumentException('API ID, login and password are required.');
        }
    }

    /** Process environment takes precedence over the project .env file. */
    public static function fromEnvironment(?string $envFile = null): self
    {
        $values = EnvFile::read($envFile ?? dirname(__DIR__) . '/.env');
        $read = static function (string $key) use ($values): string {
            $value = getenv($key);
            return $value !== false ? $value : ($values[$key] ?? '');
        };
        return new self($read('DREB_API_ID'), $read('DREB_LOGIN'), $read('DREB_PASSWORD'));
    }

    public static function demo(): self
    {
        return new self('demo_api', 'demo@example.com', 'demo');
    }

    /** @internal */
    public function arguments(): array
    {
        return [$this->apiId, $this->login, $this->password];
    }

    public function __debugInfo(): array
    {
        return ['credentials' => '[redacted]'];
    }
}
