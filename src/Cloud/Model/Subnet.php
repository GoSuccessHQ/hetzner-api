<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use GoSuccess\Hetzner\Json;

/**
 * A subnet of a network in one network zone.
 */
final readonly class Subnet
{
    /**
     * @param string      $type    `cloud`, `server` or `vswitch`.
     * @param string|null $ipRange Null while Hetzner has not assigned one.
     */
    public function __construct(
        public string $type,
        public ?string $ipRange,
        public string $networkZone,
        public string $gateway,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        return new self(
            Json::string($data, 'type', $path),
            Json::nullableString($data, 'ip_range', $path),
            Json::string($data, 'network_zone', $path),
            Json::string($data, 'gateway', $path),
        );
    }
}
