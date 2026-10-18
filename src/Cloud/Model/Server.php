<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use DateTimeImmutable;
use GoSuccess\Hetzner\Json;

/**
 * A server. Type, location, image and placement group are given by name.
 */
final readonly class Server
{
    /**
     * @param string                $status          `running`, `initializing`, `starting`, `stopping`,
     *                                               `off`, `deleting`, `migrating`, `rebuilding` or `unknown`.
     * @param string|null           $ipv4            Null without a public IPv4.
     * @param string|null           $ipv6            The /64 network, e.g. `2001:db8::/64`.
     * @param string|null           $image           Null for a server without image, or a snapshot without name.
     * @param array<string, string> $labels
     * @param string|null           $backupWindow    Null while backups are off.
     * @param list<int>             $privateNetworks IDs of the attached networks.
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $status,
        public ?string $ipv4,
        public ?string $ipv6,
        public string $location,
        public string $serverType,
        public ?string $image,
        public ?string $placementGroup,
        public array $labels,
        public DateTimeImmutable $created,
        public ServerProtection $protection,
        public ?string $backupWindow,
        public array $privateNetworks,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        $net = Json::object($data, 'public_net', $path);
        $ipv4 = Json::nullableObject($net, 'ipv4', "{$path}.public_net");
        $ipv6 = Json::nullableObject($net, 'ipv6', "{$path}.public_net");
        $image = Json::nullableObject($data, 'image', $path);
        $placementGroup = Json::nullableObject($data, 'placement_group', $path);
        $protection = Json::object($data, 'protection', $path);
        $networks = [];

        foreach (Json::list($data, 'private_net', $path) as $index => $attachment) {
            $network = Json::nullableInt($attachment, 'network', "{$path}.private_net[{$index}]");

            if ($network !== null) {
                $networks[] = $network;
            }
        }

        return new self(
            Json::int($data, 'id', $path),
            Json::string($data, 'name', $path),
            Json::string($data, 'status', $path),
            $ipv4 === null ? null : Json::string($ipv4, 'ip', "{$path}.public_net.ipv4"),
            $ipv6 === null ? null : Json::string($ipv6, 'ip', "{$path}.public_net.ipv6"),
            Json::string(Json::object($data, 'location', $path), 'name', "{$path}.location"),
            Json::string(Json::object($data, 'server_type', $path), 'name', "{$path}.server_type"),
            $image === null ? null : Json::nullableString($image, 'name', "{$path}.image"),
            $placementGroup === null ? null : Json::string($placementGroup, 'name', "{$path}.placement_group"),
            Json::labels($data, 'labels', $path),
            Json::time($data, 'created', $path),
            new ServerProtection(Json::bool($protection, 'delete', "{$path}.protection"), Json::bool($protection, 'rebuild', "{$path}.protection")),
            Json::nullableString($data, 'backup_window', $path),
            $networks,
        );
    }
}
