<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use GoSuccess\Hetzner\Json;

/**
 * A server type (`cx23`, `cpx22`, …) with its prices and locations.
 */
final readonly class ServerType
{
    /**
     * @param float                    $memory    In GB.
     * @param int                      $disk      In GB.
     * @param string                   $cpuType   `shared` or `dedicated`.
     * @param string                   $storageType `local` or `network`.
     * @param string                   $architecture `x86` or `arm`.
     * @param list<Price>              $prices
     * @param list<ServerTypeLocation> $locations
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public int $cores,
        public float $memory,
        public int $disk,
        public string $cpuType,
        public string $storageType,
        public string $architecture,
        public ?Deprecation $deprecation,
        public array $prices,
        public array $locations,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        $locations = [];

        foreach (Json::list($data, 'locations', $path) as $index => $location) {
            $locations[] = ServerTypeLocation::fromJson($location, "{$path}.locations[{$index}]");
        }

        return new self(
            Json::int($data, 'id', $path),
            Json::string($data, 'name', $path),
            Json::string($data, 'description', $path),
            Json::int($data, 'cores', $path),
            Json::float($data, 'memory', $path),
            Json::int($data, 'disk', $path),
            Json::string($data, 'cpu_type', $path),
            Json::string($data, 'storage_type', $path),
            Json::string($data, 'architecture', $path),
            Deprecation::fromNullable($data, 'deprecation', $path),
            Price::listFrom($data, 'prices', $path),
            $locations,
        );
    }
}
