<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Tests\Support;

use RuntimeException;
use stdClass;

/**
 * Builds example documents from the committed snapshot of the Cloud API
 * specification: every property with the spec's own `example`, otherwise a
 * value of its type. Tests feed them to the client, so a model is tested
 * against the shape Hetzner documents, not a hand-written fixture.
 */
final class SpecExample
{
    private const string SPEC = __DIR__ . '/../../resources/specs/cloud.spec.json';

    /** @var array<string, mixed>|null */
    private static ?array $spec = null;

    /**
     * The decoded specification, read once per process.
     *
     * @return array<string, mixed>
     */
    public static function spec(): array
    {
        if (self::$spec === null) {
            $decoded = json_decode((string) file_get_contents(self::SPEC), true, 512, \JSON_THROW_ON_ERROR);

            if (!\is_array($decoded)) {
                throw new RuntimeException('The specification is not a JSON object.');
            }

            /** @var array<string, mixed> $decoded */
            self::$spec = $decoded;
        }

        return self::$spec;
    }

    /**
     * Method, path and definition of an operation.
     *
     * @return array{method: string, path: string, operation: array<string, mixed>}
     */
    public static function operation(string $operationId): array
    {
        $paths = self::spec()['paths'] ?? [];

        foreach (\is_array($paths) ? $paths : [] as $path => $item) {
            foreach (\is_array($item) ? $item : [] as $method => $operation) {
                if (\is_array($operation) && ($operation['operationId'] ?? null) === $operationId) {
                    /** @var array<string, mixed> $operation */
                    return ['method' => strtoupper((string) $method), 'path' => (string) $path, 'operation' => $operation];
                }
            }
        }

        throw new RuntimeException("No operation {$operationId} in the specification.");
    }

    /**
     * The schema of the first 2xx response with a JSON body.
     *
     * @return array<string, mixed>
     */
    public static function responseSchema(string $operationId): array
    {
        $responses = self::operation($operationId)['operation']['responses'] ?? [];

        foreach (\is_array($responses) ? $responses : [] as $status => $response) {
            $schema = self::at($response, 'content', 'application/json', 'schema');

            if (str_starts_with((string) $status, '2') && \is_array($schema)) {
                /** @var array<string, mixed> $schema */
                return $schema;
            }
        }

        throw new RuntimeException("{$operationId} has no JSON success response.");
    }

    /**
     * An example of the first 2xx response; a list's `meta.pagination` is set
     * to a single page, so walking it ends.
     *
     * @return array<string, mixed>
     */
    public static function response(string $operationId): array
    {
        $example = json_decode((string) json_encode(self::build(self::responseSchema($operationId)), \JSON_THROW_ON_ERROR), true);

        if (!\is_array($example)) {
            throw new RuntimeException("The example of {$operationId} is not an object.");
        }

        if (isset($example['meta']) && \is_array($example['meta']) && isset($example['meta']['pagination'])) {
            $example['meta']['pagination'] = ['page' => 1, 'per_page' => 50, 'previous_page' => null, 'next_page' => null, 'last_page' => 1, 'total_entries' => 1];
        }

        /** @var array<string, mixed> $example */
        return $example;
    }

    /**
     * The example as a response body.
     */
    public static function json(string $operationId): string
    {
        return (string) json_encode(self::response($operationId), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * A copy of `$data` with the value at a dotted path (`server_types.0.name`)
     * replaced, so a test can break one field of an example.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public static function with(array $data, string $path, mixed $value): array
    {
        $keys = explode('.', $path);
        $key = array_shift($keys);

        if ($keys === []) {
            $data[$key] = $value;

            return $data;
        }

        $inner = $data[$key] ?? null;
        $data[$key] = self::with(\is_array($inner) ? $inner : [], implode('.', $keys), $value);

        return $data;
    }

    /**
     * A copy of `$data` without the key at a dotted path, for optional fields.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public static function without(array $data, string $path): array
    {
        $keys = explode('.', $path);
        $key = array_shift($keys);

        if ($keys === []) {
            unset($data[$key]);

            return $data;
        }

        $inner = $data[$key] ?? null;
        $data[$key] = self::without(\is_array($inner) ? $inner : [], implode('.', $keys));

        return $data;
    }

    /**
     * The value below nested keys, or null.
     */
    public static function at(mixed $value, string|int ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $schema
     */
    private static function build(array $schema): mixed
    {
        if (\array_key_exists('example', $schema)) {
            return $schema['example'];
        }

        if (isset($schema['allOf']) && \is_array($schema['allOf'])) {
            $merged = [];

            foreach ($schema['allOf'] as $part) {
                $value = \is_array($part) ? self::build($part) : null;
                $merged = [...$merged, ...(\is_array($value) ? $value : [])];
            }

            return $merged;
        }

        foreach (['oneOf', 'anyOf'] as $keyword) {
            $first = self::at($schema, $keyword, 0);

            if (\is_array($first)) {
                return self::build($first);
            }
        }

        if (isset($schema['enum']) && \is_array($schema['enum']) && $schema['enum'] !== []) {
            return $schema['enum'][0];
        }

        $types = array_filter((array) ($schema['type'] ?? 'object'), static fn(mixed $type): bool => \is_string($type) && $type !== 'null');
        $type = array_values($types)[0] ?? 'null';

        return match ($type) {
            'object' => self::object($schema),
            'array' => [self::build(\is_array($schema['items'] ?? null) ? $schema['items'] : [])],
            'string' => ($schema['format'] ?? null) === 'date-time' ? '2016-01-30T23:55:00Z' : 'string',
            'integer' => 1,
            'number' => 1.5,
            'boolean' => false,
            default => null,
        };
    }

    /**
     * @param array<array-key, mixed> $schema
     *
     * @return array<string, mixed>|object
     */
    private static function object(array $schema): array|object
    {
        $object = [];

        foreach (\is_array($schema['properties'] ?? null) ? $schema['properties'] : [] as $name => $property) {
            $object[(string) $name] = \is_array($property) ? self::build($property) : null;
        }

        // An object without properties (labels) stays an object in JSON.
        return $object === [] ? new stdClass() : $object;
    }
}
