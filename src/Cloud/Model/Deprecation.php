<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use DateTimeImmutable;
use GoSuccess\Hetzner\Json;

/**
 * A deprecation Hetzner announced: from `unavailableAfter` the resource can no
 * longer be ordered there.
 */
final readonly class Deprecation
{
    public function __construct(
        public DateTimeImmutable $announced,
        public DateTimeImmutable $unavailableAfter,
    ) {}

    /**
     * @internal
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromNullable(array $data, string $key, string $path): ?self
    {
        $deprecation = Json::nullableObject($data, $key, $path);
        $path = Json::join($path, $key);

        return $deprecation === null ? null : new self(Json::time($deprecation, 'announced', $path), Json::time($deprecation, 'unavailable_after', $path));
    }
}
