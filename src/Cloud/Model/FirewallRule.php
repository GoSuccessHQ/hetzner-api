<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use GoSuccess\Hetzner\Exception\SerializationException;
use GoSuccess\Hetzner\Json;
use InvalidArgumentException;

/**
 * A rule that allows traffic. Incoming rules match `sourceIps`, outgoing
 * rules `destinationIps`; only TCP and UDP take a port (`80`, `80-85`, `any`).
 */
final readonly class FirewallRule
{
    /**
     * @param list<string> $sourceIps      Networks in CIDR notation, for `in`.
     * @param list<string> $destinationIps Networks in CIDR notation, for `out`.
     */
    public function __construct(
        public FirewallDirection $direction,
        public FirewallProtocol $protocol,
        public ?string $port = null,
        public array $sourceIps = [],
        public array $destinationIps = [],
        public ?string $description = null,
    ) {
        if ($port !== null && !$protocol->hasPorts()) {
            throw new InvalidArgumentException("A {$protocol->value} rule takes no port.");
        }
    }

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        $direction = FirewallDirection::tryFrom(Json::string($data, 'direction', $path))
            ?? throw new SerializationException("Field {$path}.direction must be in or out.");
        $protocol = FirewallProtocol::tryFrom(Json::string($data, 'protocol', $path))
            ?? throw new SerializationException("Field {$path}.protocol must be tcp, udp, icmp, esp or gre.");

        return new self(
            $direction,
            $protocol,
            $protocol->hasPorts() ? Json::nullableString($data, 'port', $path) : null,
            Json::strings($data, 'source_ips', $path),
            Json::strings($data, 'destination_ips', $path),
            Json::nullableString($data, 'description', $path),
        );
    }

    /**
     * The rule as Hetzner takes it: only the address list of its direction,
     * port and description only when set.
     *
     * @internal
     *
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        $rule = ['direction' => $this->direction->value, 'protocol' => $this->protocol->value];

        if ($this->port !== null) {
            $rule['port'] = $this->port;
        }

        if ($this->direction === FirewallDirection::In) {
            $rule['source_ips'] = $this->sourceIps;
        } else {
            $rule['destination_ips'] = $this->destinationIps;
        }

        if ($this->description !== null) {
            $rule['description'] = $this->description;
        }

        return $rule;
    }
}
