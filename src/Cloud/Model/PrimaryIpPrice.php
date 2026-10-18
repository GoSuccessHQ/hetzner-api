<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use GoSuccess\Hetzner\Json;

/**
 * The prices of a primary IP type per location.
 */
final readonly class PrimaryIpPrice
{
    /**
     * @param string      $type   `ipv4` or `ipv6`.
     * @param list<Price> $prices
     */
    public function __construct(
        public string $type,
        public array $prices,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        return new self(Json::string($data, 'type', $path), Price::listFrom($data, 'prices', $path));
    }
}
