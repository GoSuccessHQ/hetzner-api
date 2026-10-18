<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use DateTimeImmutable;
use GoSuccess\Hetzner\Json;

/**
 * A placement group; `spread` puts its servers on different hosts.
 */
final readonly class PlacementGroup
{
    /**
     * @param list<int>             $servers
     * @param array<string, string> $labels
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $type,
        public array $servers,
        public array $labels,
        public DateTimeImmutable $created,
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
            Json::string($data, 'type', $path),
            Json::ints($data, 'servers', $path),
            Json::labels($data, 'labels', $path),
            Json::time($data, 'created', $path),
        );
    }
}
