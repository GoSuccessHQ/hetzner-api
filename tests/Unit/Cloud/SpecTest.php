<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Tests\Unit\Cloud;

use GoSuccess\Hetzner\Tests\Support\SpecExample;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins every operation the client calls, every field its models read and
 * every field it sends against the committed snapshot of the specification.
 * The weekly spec-drift workflow refreshes the snapshot; when Hetzner drops
 * or changes one of these, this test names it.
 *
 * Modes: `R` required and never null (the model reads it as such), `N` may be
 * null or absent (read as nullable), `O` may be absent (read only when there).
 */
#[CoversNothing]
final class SpecTest extends TestCase
{
    /** @var array<string, array{string, string}> operationId => [method, path] */
    private const array OPERATIONS = [
        'list_locations' => ['GET', '/locations'],
        'get_location' => ['GET', '/locations/{id}'],
        'list_server_types' => ['GET', '/server_types'],
        'list_images' => ['GET', '/images'],
        'list_networks' => ['GET', '/networks'],
        'list_placement_groups' => ['GET', '/placement_groups'],
        'list_ssh_keys' => ['GET', '/ssh_keys'],
        'get_pricing' => ['GET', '/pricing'],
        'get_action' => ['GET', '/actions/{id}'],
        'list_servers' => ['GET', '/servers'],
        'get_server' => ['GET', '/servers/{id}'],
        'create_server' => ['POST', '/servers'],
        'update_server' => ['PUT', '/servers/{id}'],
        'delete_server' => ['DELETE', '/servers/{id}'],
        'enable_server_backup' => ['POST', '/servers/{id}/actions/enable_backup'],
        'change_server_protection' => ['POST', '/servers/{id}/actions/change_protection'],
        'list_firewalls' => ['GET', '/firewalls'],
        'get_firewall' => ['GET', '/firewalls/{id}'],
        'create_firewall' => ['POST', '/firewalls'],
        'set_firewall_rules' => ['POST', '/firewalls/{id}/actions/set_rules'],
        'apply_firewall_to_resources' => ['POST', '/firewalls/{id}/actions/apply_to_resources'],
        'remove_firewall_from_resources' => ['POST', '/firewalls/{id}/actions/remove_from_resources'],
        'delete_firewall' => ['DELETE', '/firewalls/{id}'],
    ];

    private const array PAGINATION = [
        'meta.pagination.page' => 'R', 'meta.pagination.per_page' => 'R', 'meta.pagination.previous_page' => 'N',
        'meta.pagination.next_page' => 'N', 'meta.pagination.last_page' => 'N', 'meta.pagination.total_entries' => 'N',
    ];

    private const array LOCATION = [
        'id' => 'R', 'name' => 'R', 'description' => 'R', 'country' => 'R', 'city' => 'R', 'latitude' => 'R', 'longitude' => 'R', 'network_zone' => 'R',
    ];

    private const array PRICE = [
        'location' => 'R', 'price_hourly.net' => 'R', 'price_hourly.gross' => 'R', 'price_monthly.net' => 'R', 'price_monthly.gross' => 'R',
    ];

    private const array SERVER_TYPE = [
        'id' => 'R', 'name' => 'R', 'description' => 'R', 'cores' => 'R', 'memory' => 'R', 'disk' => 'R', 'cpu_type' => 'R',
        'storage_type' => 'R', 'architecture' => 'R', 'deprecation' => 'N', 'deprecation.announced' => 'R', 'deprecation.unavailable_after' => 'R',
        'prices' => 'R', 'locations' => 'R', 'locations[].id' => 'R', 'locations[].name' => 'R', 'locations[].deprecation' => 'N',
        'locations[].deprecation.announced' => 'R', 'locations[].deprecation.unavailable_after' => 'R', 'locations[].recommended' => 'R', 'locations[].available' => 'R',
    ];

    private const array IMAGE = [
        'id' => 'R', 'type' => 'R', 'status' => 'R', 'name' => 'N', 'description' => 'R', 'os_flavor' => 'R', 'os_version' => 'N',
        'architecture' => 'R', 'deprecated' => 'N', 'labels' => 'R', 'created' => 'R',
    ];

    private const array NETWORK = [
        'id' => 'R', 'name' => 'R', 'ip_range' => 'R', 'subnets' => 'R', 'subnets[].type' => 'R', 'subnets[].ip_range' => 'N',
        'subnets[].network_zone' => 'R', 'subnets[].gateway' => 'R', 'servers' => 'R', 'labels' => 'R', 'created' => 'R',
    ];

    private const array PLACEMENT_GROUP = ['id' => 'R', 'name' => 'R', 'type' => 'R', 'servers' => 'R', 'labels' => 'R', 'created' => 'R'];

    private const array SSH_KEY = ['id' => 'R', 'name' => 'R', 'fingerprint' => 'R', 'public_key' => 'R', 'labels' => 'R', 'created' => 'R'];

    private const array PRICING = [
        'currency' => 'R', 'vat_rate' => 'R', 'server_backup.percentage' => 'R', 'primary_ips' => 'R', 'primary_ips[].type' => 'R', 'primary_ips[].prices' => 'R',
    ];

    private const array ACTION = [
        'id' => 'R', 'command' => 'R', 'status' => 'R', 'progress' => 'R', 'started' => 'R', 'finished' => 'N', 'resources' => 'R',
        'resources[].id' => 'R', 'resources[].type' => 'R', 'error' => 'N', 'error.code' => 'R', 'error.message' => 'R',
    ];

    private const array SERVER = [
        'id' => 'R', 'name' => 'R', 'status' => 'R', 'created' => 'R', 'public_net' => 'R', 'public_net.ipv4' => 'N', 'public_net.ipv4.ip' => 'R',
        'public_net.ipv6' => 'N', 'public_net.ipv6.ip' => 'R', 'private_net' => 'R', 'private_net[].network' => 'N', 'location.name' => 'R',
        'server_type.name' => 'R', 'image' => 'N', 'image.name' => 'N', 'placement_group' => 'N', 'placement_group.name' => 'R', 'labels' => 'R',
        'protection.delete' => 'R', 'protection.rebuild' => 'R', 'backup_window' => 'N',
    ];

    private const array FIREWALL = [
        'id' => 'R', 'name' => 'R', 'labels' => 'O', 'created' => 'R', 'rules' => 'R', 'rules[].direction' => 'R', 'rules[].protocol' => 'R',
        'rules[].port' => 'N', 'rules[].source_ips' => 'R', 'rules[].destination_ips' => 'R', 'rules[].description' => 'N', 'applied_to' => 'R',
        'applied_to[].type' => 'R', 'applied_to[].server' => 'O', 'applied_to[].server.id' => 'R', 'applied_to[].label_selector' => 'O',
        'applied_to[].label_selector.selector' => 'R', 'applied_to[].applied_to_resources' => 'O', 'applied_to[].applied_to_resources[].server.id' => 'R',
    ];

    private const array RULE_BODY = ['direction' => 'O', 'protocol' => 'O', 'port' => 'O', 'source_ips' => 'O', 'destination_ips' => 'O', 'description' => 'O'];

    private const array TARGET_BODY = ['type' => 'O', 'server.id' => 'O', 'label_selector.selector' => 'O'];

    #[DataProvider('operations')]
    public function testOperationExists(string $operationId, string $method, string $path): void
    {
        $operation = SpecExample::operation($operationId);

        self::assertSame([$method, $path], [$operation['method'], $operation['path']]);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function operations(): iterable
    {
        foreach (self::OPERATIONS as $id => [$method, $path]) {
            yield $id => [$id, $method, $path];
        }
    }

    #[DataProvider('responseFields')]
    public function testResponseFieldMatchesTheModel(string $operationId, string $field, string $mode): void
    {
        self::assertSame([], self::check(SpecExample::responseSchema($operationId), $field, $mode));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function responseFields(): iterable
    {
        $models = [
            'list_locations' => ['locations[]' => self::LOCATION, '' => self::PAGINATION],
            'get_location' => ['location' => self::LOCATION],
            'list_server_types' => ['server_types[]' => self::SERVER_TYPE, 'server_types[].prices[]' => self::PRICE, '' => self::PAGINATION],
            'list_images' => ['images[]' => self::IMAGE, '' => self::PAGINATION],
            'list_networks' => ['networks[]' => self::NETWORK, '' => self::PAGINATION],
            'list_placement_groups' => ['placement_groups[]' => self::PLACEMENT_GROUP, '' => self::PAGINATION],
            'list_ssh_keys' => ['ssh_keys[]' => self::SSH_KEY, '' => self::PAGINATION],
            'get_pricing' => ['pricing' => self::PRICING, 'pricing.primary_ips[].prices[]' => self::PRICE],
            'get_action' => ['action' => self::ACTION],
            'list_servers' => ['servers[]' => self::SERVER, '' => self::PAGINATION],
            'get_server' => ['server' => self::SERVER],
            'create_server' => ['server' => self::SERVER, 'action' => self::ACTION, 'next_actions[]' => self::ACTION, '' => ['root_password' => 'N']],
            'update_server' => ['server' => self::SERVER],
            'enable_server_backup' => ['action' => self::ACTION],
            'change_server_protection' => ['action' => self::ACTION],
            'list_firewalls' => ['firewalls[]' => self::FIREWALL, '' => self::PAGINATION],
            'get_firewall' => ['firewall' => self::FIREWALL],
            'create_firewall' => ['firewall' => self::FIREWALL, 'actions[]' => self::ACTION],
            'set_firewall_rules' => ['actions[]' => self::ACTION],
            'apply_firewall_to_resources' => ['actions[]' => self::ACTION],
            'remove_firewall_from_resources' => ['actions[]' => self::ACTION],
        ];

        foreach ($models as $operationId => $prefixes) {
            foreach ($prefixes as $prefix => $fields) {
                foreach ($fields as $field => $mode) {
                    $path = $prefix === '' ? $field : (str_starts_with($field, '[') ? $prefix . $field : "{$prefix}.{$field}");

                    yield "{$operationId} {$path}" => [$operationId, $path, $mode];
                }
            }
        }
    }

    #[DataProvider('requestFields')]
    public function testRequestFieldExists(string $operationId, string $field): void
    {
        $body = SpecExample::at(SpecExample::operation($operationId)['operation'], 'requestBody', 'content', 'application/json', 'schema');

        self::assertIsArray($body, "{$operationId} has no JSON request body.");
        self::assertSame([], self::check($body, $field, 'O'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function requestFields(): iterable
    {
        $bodies = [
            'create_server' => ['name', 'server_type', 'image', 'location', 'ssh_keys', 'firewalls[].firewall', 'labels', 'public_net.enable_ipv4', 'public_net.enable_ipv6', 'start_after_create', 'networks', 'placement_group', 'user_data'],
            'update_server' => ['labels'],
            'change_server_protection' => ['delete', 'rebuild'],
            'create_firewall' => ['name', 'labels', ...self::prefixed('rules[]', self::RULE_BODY), ...self::prefixed('apply_to[]', self::TARGET_BODY)],
            'set_firewall_rules' => self::prefixed('rules[]', self::RULE_BODY),
            'apply_firewall_to_resources' => self::prefixed('apply_to[]', self::TARGET_BODY),
            'remove_firewall_from_resources' => self::prefixed('remove_from[]', self::TARGET_BODY),
        ];

        foreach ($bodies as $operationId => $fields) {
            foreach ($fields as $field) {
                yield "{$operationId} {$field}" => [$operationId, $field];
            }
        }
    }

    public function testErrorEnvelope(): void
    {
        $schema = SpecExample::at(SpecExample::operation('get_server')['operation'], 'responses', '4xx', 'content', 'application/json', 'schema');

        self::assertIsArray($schema);
        self::assertSame([], self::check($schema, 'error.code', 'R'));
        self::assertSame([], self::check($schema, 'error.message', 'R'));
    }

    /**
     * @param array<string, string> $fields
     *
     * @return list<string>
     */
    private static function prefixed(string $prefix, array $fields): array
    {
        return array_map(static fn(string $field): string => "{$prefix}.{$field}", array_keys($fields));
    }

    /**
     * The problems of a field in a schema; empty when it fits the mode.
     *
     * @param array<array-key, mixed> $schema
     *
     * @return list<string>
     */
    private static function check(array $schema, string $field, string $mode): array
    {
        $segments = preg_split('/\.|(?=\[\])/', $field) ?: [];
        $required = true;
        $nullable = false;

        foreach ($segments as $segment) {
            $schema = self::merged($schema);

            if ($segment === '[]') {
                if (!\in_array('array', (array) ($schema['type'] ?? []), true) || !\is_array($schema['items'] ?? null)) {
                    return ["{$field}: not a list"];
                }

                $schema = $schema['items'];
                $required = true;
                $nullable = false;

                continue;
            }

            $properties = \is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

            if (!\is_array($properties[$segment] ?? null)) {
                return ["{$field}: missing"];
            }

            $required = \in_array($segment, \is_array($schema['required'] ?? null) ? $schema['required'] : [], true);
            $schema = $properties[$segment];
            $nullable = \in_array('null', (array) ($schema['type'] ?? []), true) || ($schema['nullable'] ?? false) === true;
        }

        if ($mode === 'R' && (!$required || $nullable)) {
            return ["{$field}: the model needs it, but it is " . ($required ? 'nullable' : 'optional')];
        }

        return [];
    }

    /**
     * A schema with its `allOf` parts merged.
     *
     * @param array<array-key, mixed> $schema
     *
     * @return array<array-key, mixed>
     */
    private static function merged(array $schema): array
    {
        if (!\is_array($schema['allOf'] ?? null)) {
            return $schema;
        }

        $merged = $schema;
        unset($merged['allOf']);

        foreach ($schema['allOf'] as $part) {
            $part = \is_array($part) ? self::merged($part) : [];
            $merged['properties'] = [...(\is_array($merged['properties'] ?? null) ? $merged['properties'] : []), ...(\is_array($part['properties'] ?? null) ? $part['properties'] : [])];
            $merged['required'] = [...(\is_array($merged['required'] ?? null) ? $merged['required'] : []), ...(\is_array($part['required'] ?? null) ? $part['required'] : [])];
            $merged['type'] ??= $part['type'] ?? null;
        }

        return $merged;
    }
}
