<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use GoSuccess\Hetzner\Exception\SerializationException;
use GoSuccess\Hetzner\Json;

/**
 * What a firewall applies to: one server, or every server a label selector
 * matches ({@see $coveredServerIds}, as Hetzner reports them).
 */
final readonly class FirewallTarget
{
    /**
     * @param list<int> $coveredServerIds For a label selector: the servers it matches.
     */
    public function __construct(
        public FirewallTargetType $type,
        public ?int $serverId = null,
        public ?string $labelSelector = null,
        public array $coveredServerIds = [],
    ) {}

    /**
     * One server, applied directly.
     */
    public static function server(int $id): self
    {
        return new self(FirewallTargetType::Server, serverId: $id);
    }

    /**
     * Every server whose labels match, e.g. `gosuccess-role=wpaas`.
     */
    public static function labelSelector(string $selector): self
    {
        return new self(FirewallTargetType::LabelSelector, labelSelector: $selector);
    }

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        $type = FirewallTargetType::tryFrom(Json::string($data, 'type', $path))
            ?? throw new SerializationException("Field {$path}.type must be server or label_selector.");

        if ($type === FirewallTargetType::Server) {
            return self::server(Json::int(Json::object($data, 'server', $path), 'id', "{$path}.server"));
        }

        $covered = [];

        foreach (\array_key_exists('applied_to_resources', $data) ? Json::list($data, 'applied_to_resources', $path) : [] as $index => $resource) {
            $resourcePath = "{$path}.applied_to_resources[{$index}]";

            if (($resource['type'] ?? null) === 'server') {
                $covered[] = Json::int(Json::object($resource, 'server', $resourcePath), 'id', "{$resourcePath}.server");
            }
        }

        return new self($type, null, Json::string(Json::object($data, 'label_selector', $path), 'selector', "{$path}.label_selector"), $covered);
    }

    /**
     * @internal
     *
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return $this->type === FirewallTargetType::Server
            ? ['type' => 'server', 'server' => ['id' => $this->serverId]]
            : ['type' => 'label_selector', 'label_selector' => ['selector' => $this->labelSelector]];
    }
}
