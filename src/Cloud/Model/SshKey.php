<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use DateTimeImmutable;
use GoSuccess\Hetzner\Json;

/**
 * An SSH key of the project.
 */
final readonly class SshKey
{
    /**
     * @param array<string, string> $labels
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $fingerprint,
        public string $publicKey,
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
            Json::string($data, 'fingerprint', $path),
            Json::string($data, 'public_key', $path),
            Json::labels($data, 'labels', $path),
            Json::time($data, 'created', $path),
        );
    }
}
