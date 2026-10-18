<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use GoSuccess\Hetzner\Json;

/**
 * A location (`fsn1`, `nbg1`, `hel1`, …) and the network zone it belongs to.
 */
final readonly class Location
{
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public string $country,
        public string $city,
        public float $latitude,
        public float $longitude,
        public string $networkZone,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        return new self(
            Json::int($data, 'id', $path),
            Json::string($data, 'name', $path),
            Json::string($data, 'description', $path),
            Json::string($data, 'country', $path),
            Json::string($data, 'city', $path),
            Json::float($data, 'latitude', $path),
            Json::float($data, 'longitude', $path),
            Json::string($data, 'network_zone', $path),
        );
    }
}
