<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use DateTimeImmutable;
use GoSuccess\Hetzner\Json;

/**
 * An image: a system image (`ubuntu-24.04`), an app, a snapshot or a backup.
 */
final readonly class Image
{
    /**
     * @param string                $type     `system`, `app`, `snapshot` or `backup`.
     * @param string                $status   `available`, `creating` or `unavailable`.
     * @param string|null           $name     Null for snapshots and backups.
     * @param string                $osFlavor `ubuntu`, `debian`, … or `unknown`.
     * @param array<string, string> $labels
     */
    public function __construct(
        public int $id,
        public string $type,
        public string $status,
        public ?string $name,
        public string $description,
        public string $osFlavor,
        public ?string $osVersion,
        public string $architecture,
        public ?DateTimeImmutable $deprecated,
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
            Json::string($data, 'type', $path),
            Json::string($data, 'status', $path),
            Json::nullableString($data, 'name', $path),
            Json::string($data, 'description', $path),
            Json::string($data, 'os_flavor', $path),
            Json::nullableString($data, 'os_version', $path),
            Json::string($data, 'architecture', $path),
            Json::nullableTime($data, 'deprecated', $path),
            Json::labels($data, 'labels', $path),
            Json::time($data, 'created', $path),
        );
    }
}
