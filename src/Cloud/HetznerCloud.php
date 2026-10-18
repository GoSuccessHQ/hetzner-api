<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud;

use Closure;
use GoSuccess\Hetzner\ClientOptions;
use GoSuccess\Hetzner\Cloud\Resource\Actions;
use GoSuccess\Hetzner\Cloud\Resource\Images;
use GoSuccess\Hetzner\Cloud\Resource\Locations;
use GoSuccess\Hetzner\Cloud\Resource\Networks;
use GoSuccess\Hetzner\Cloud\Resource\PlacementGroups;
use GoSuccess\Hetzner\Cloud\Resource\Pricing;
use GoSuccess\Hetzner\Cloud\Resource\Servers;
use GoSuccess\Hetzner\Cloud\Resource\ServerTypes;
use GoSuccess\Hetzner\Cloud\Resource\SshKeys;
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
     * Locations (`/locations`).
     */
    public function locations(): Locations
    {
        return new Locations($this->connection);
    }

    /**
     * Server types with prices and availability (`/server_types`).
     */
    public function serverTypes(): ServerTypes
    {
        return new ServerTypes($this->connection);
    }

    /**
     * Images (`/images`).
     */
    public function images(): Images
    {
        return new Images($this->connection);
    }

    /**
     * Private networks (`/networks`).
     */
    public function networks(): Networks
    {
        return new Networks($this->connection);
    }

    /**
     * Placement groups (`/placement_groups`).
     */
    public function placementGroups(): PlacementGroups
    {
        return new PlacementGroups($this->connection);
    }

    /**
     * SSH keys (`/ssh_keys`).
     */
    public function sshKeys(): SshKeys
    {
        return new SshKeys($this->connection);
    }

    /**
     * Prices of the project (`/pricing`).
     */
    public function pricing(): Pricing
    {
        return new Pricing($this->connection);
    }

    /**
     * Servers (`/servers`).
     */
    public function servers(): Servers
    {
        return new Servers($this->connection);
    }

    /**
     * Actions, and waiting for them (`/actions`).
     */
    public function actions(): Actions
    {
        return new Actions($this->connection);
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
