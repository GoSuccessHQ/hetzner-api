<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud;

use Closure;
use GoSuccess\Hetzner\ClientOptions;
use GoSuccess\Hetzner\Http\Connection;
use GoSuccess\Hetzner\Http\CurlHttpClient;
use GoSuccess\Hetzner\Http\HttpClient;
use GoSuccess\Hetzner\RateLimit;
use SensitiveParameter;

/**
 * Entry point of the Hetzner Cloud API (`https://api.hetzner.cloud/v1`): one
 * project, one token. Hands out the resources.
 */
final class HetznerCloud
{
    public const string BASE_URL = 'https://api.hetzner.cloud/v1';

    private readonly Connection $connection;

    /**
     * @param string                      $token      An API token of the project (Security → API tokens).
     * @param HttpClient|null             $httpClient Transport; defaults to {@see CurlHttpClient}.
     * @param (Closure(float): void)|null $sleep      Waits between retries and while polling actions;
     *                                                replaceable for tests.
     */
    public function __construct(
        #[SensitiveParameter]
        string $token,
        ClientOptions $options = new ClientOptions(),
        ?HttpClient $httpClient = null,
        ?Closure $sleep = null,
    ) {
        $this->connection = new Connection(
            self::BASE_URL,
            $token,
            $options,
            $httpClient ?? new CurlHttpClient($options->timeout, $options->connectTimeout),
            $sleep,
        );
    }

    /**
     * The rate limit as the last response reported it; null before the first one.
     */
    public function rateLimit(): ?RateLimit
    {
        return $this->connection->lastRateLimit();
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['baseUrl' => self::BASE_URL, 'token' => '********'];
    }
}
