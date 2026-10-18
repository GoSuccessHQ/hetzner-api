<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use SensitiveParameter;

/**
 * An order for a server (`POST /servers`).
 */
final readonly class CreateServer
{
    /**
     * @param string                $serverType E.g. `cx23`.
     * @param string                $image      Name or ID of the image, e.g. `ubuntu-24.04`.
     * @param string|null           $location   Null lets Hetzner choose.
     * @param list<int|string>      $sshKeys    IDs or names.
     * @param list<int>             $firewalls  IDs of firewalls to apply.
     * @param list<int>             $networks   IDs of networks to attach.
     * @param array<string, string> $labels
     * @param string|null           $userData   Cloud-init data; may carry secrets, never shown in dumps.
     */
    public function __construct(
        public string $name,
        public string $serverType,
        public string $image,
        public ?string $location = null,
        public array $sshKeys = [],
        public array $firewalls = [],
        public array $networks = [],
        public ?int $placementGroup = null,
        public array $labels = [],
        public bool $enableIpv4 = true,
        public bool $enableIpv6 = true,
        public bool $startAfterCreate = true,
        #[SensitiveParameter]
        public ?string $userData = null,
    ) {}

    /**
     * The request body; labels are always an object, optional keys are left out.
     *
     * @internal
     *
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        $body = [
            'name' => $this->name,
            'server_type' => $this->serverType,
            'image' => $this->image,
        ];

        if ($this->location !== null) {
            $body['location'] = $this->location;
        }

        $body += [
            'ssh_keys' => $this->sshKeys,
            'firewalls' => array_map(static fn(int $id): array => ['firewall' => $id], $this->firewalls),
            'labels' => (object) $this->labels,
            'public_net' => ['enable_ipv4' => $this->enableIpv4, 'enable_ipv6' => $this->enableIpv6],
            'start_after_create' => $this->startAfterCreate,
        ];

        if ($this->networks !== []) {
            $body['networks'] = $this->networks;
        }

        if ($this->placementGroup !== null) {
            $body['placement_group'] = $this->placementGroup;
        }

        if ($this->userData !== null) {
            $body['user_data'] = $this->userData;
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'name' => $this->name,
            'serverType' => $this->serverType,
            'image' => $this->image,
            'location' => $this->location,
            'sshKeys' => $this->sshKeys,
            'firewalls' => $this->firewalls,
            'networks' => $this->networks,
            'placementGroup' => $this->placementGroup,
            'labels' => $this->labels,
            'enableIpv4' => $this->enableIpv4,
            'enableIpv6' => $this->enableIpv6,
            'startAfterCreate' => $this->startAfterCreate,
            'userData' => $this->userData === null ? null : '********',
        ];
    }
}
