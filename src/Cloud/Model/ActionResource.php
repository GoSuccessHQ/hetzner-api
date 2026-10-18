<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use GoSuccess\Hetzner\Json;

/**
 * A resource an action works on.
 */
final readonly class ActionResource
{
    /**
     * @param string $type E.g. `server`, `firewall`.
     */
    public function __construct(
        public int $id,
        public string $type,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        return new self(Json::int($data, 'id', $path), Json::string($data, 'type', $path));
    }
}
