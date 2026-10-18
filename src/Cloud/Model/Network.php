<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use DateTimeImmutable;
use GoSuccess\Hetzner\Json;

/**
 * A private network with its subnets and attached servers.
 */
final readonly class Network
{
    /**
     * @param list<Subnet>          $subnets
     * @param list<int>             $servers IDs of the attached servers.
     * @param array<string, string> $labels
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $ipRange,
        public array $subnets,
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
        $subnets = [];

        foreach (Json::list($data, 'subnets', $path) as $index => $subnet) {
            $subnets[] = Subnet::fromJson($subnet, "{$path}.subnets[{$index}]");
        }

        return new self(
            Json::int($data, 'id', $path),
            Json::string($data, 'name', $path),
            Json::string($data, 'ip_range', $path),
            $subnets,
            Json::ints($data, 'servers', $path),
            Json::labels($data, 'labels', $path),
            Json::time($data, 'created', $path),
        );
    }
}
