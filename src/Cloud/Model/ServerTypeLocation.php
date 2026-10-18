<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use GoSuccess\Hetzner\Json;

/**
 * Where a server type is offered, and whether it can be ordered there now.
 */
final readonly class ServerTypeLocation
{
    /**
     * @param bool $available Whether the type can be ordered there at the moment.
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?Deprecation $deprecation,
        public bool $recommended,
        public bool $available,
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
            Deprecation::fromNullable($data, 'deprecation', $path),
            Json::bool($data, 'recommended', $path),
            Json::bool($data, 'available', $path),
        );
    }
}
