<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use DateTimeImmutable;
use GoSuccess\Hetzner\Exception\SerializationException;
use GoSuccess\Hetzner\Json;

/**
 * An asynchronous action: most changes return one, and finish later with
 * `success` or `error`.
 */
final readonly class Action
{
    /**
     * @param string               $command  E.g. `create_server`, `set_firewall_rules`.
     * @param int                  $progress 0 to 100.
     * @param list<ActionResource> $resources
     */
    public function __construct(
        public int $id,
        public string $command,
        public ActionStatus $status,
        public int $progress,
        public DateTimeImmutable $started,
        public ?DateTimeImmutable $finished,
        public array $resources,
        public ?ActionError $error,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        $status = ActionStatus::tryFrom(Json::string($data, 'status', $path));

        if ($status === null) {
            throw new SerializationException("Field {$path}.status must be running, success or error.");
        }

        $resources = [];

        foreach (Json::list($data, 'resources', $path) as $index => $resource) {
            $resources[] = ActionResource::fromJson($resource, "{$path}.resources[{$index}]");
        }

        $error = Json::nullableObject($data, 'error', $path);

        return new self(
            Json::int($data, 'id', $path),
            Json::string($data, 'command', $path),
            $status,
            Json::int($data, 'progress', $path),
            Json::time($data, 'started', $path),
            Json::nullableTime($data, 'finished', $path),
            $resources,
            $error === null ? null : new ActionError(Json::string($error, 'code', "{$path}.error"), Json::string($error, 'message', "{$path}.error")),
        );
    }

    /**
     * @internal
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<self>
     */
    public static function listFrom(array $data, string $key, string $path): array
    {
        $actions = [];

        foreach (Json::list($data, $key, $path) as $index => $action) {
            $actions[] = self::fromJson($action, Json::join(Json::join($path, $key), $index));
        }

        return $actions;
    }
}
