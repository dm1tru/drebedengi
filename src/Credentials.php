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

    public static function fromEnvironment(): self
    {
        return new self(getenv('DREB_API_ID') ?: '', getenv('DREB_LOGIN') ?: '', getenv('DREB_PASSWORD') ?: '');
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
