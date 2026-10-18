<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner;

use DateTimeImmutable;
use DateTimeZone;
use GoSuccess\Hetzner\Exception\SerializationException;

/**
 * Typed reads from decoded JSON objects. A missing or mistyped value throws a
 * {@see SerializationException} that names its path (`servers[3].server_type.name`),
 * never the value itself.
 *
 * @internal
 */
final class Json
{
    /** RFC 3339 with and without fractions; `P` also accepts `Z`. */
    private const array TIME_FORMATS = ['Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP'];

    /**
     * @param array<array-key, mixed> $data
     */
    public static function string(array $data, string|int $key, string $path): string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) ? $value : throw self::invalid($path, $key, 'a string');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function nullableString(array $data, string|int $key, string $path): ?string
    {
        return ($data[$key] ?? null) === null ? null : self::string($data, $key, $path);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function int(array $data, string|int $key, string $path): int
    {
        $value = $data[$key] ?? null;

        return \is_int($value) ? $value : throw self::invalid($path, $key, 'an integer');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function nullableInt(array $data, string|int $key, string $path): ?int
    {
        return ($data[$key] ?? null) === null ? null : self::int($data, $key, $path);
    }

    /**
     * A number; JSON does not tell `4` from `4.0`.
     *
     * @param array<array-key, mixed> $data
     */
    public static function float(array $data, string|int $key, string $path): float
    {
        $value = $data[$key] ?? null;

        return \is_int($value) || \is_float($value) ? (float) $value : throw self::invalid($path, $key, 'a number');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function bool(array $data, string|int $key, string $path): bool
    {
        $value = $data[$key] ?? null;

        return \is_bool($value) ? $value : throw self::invalid($path, $key, 'a boolean');
    }

    /**
     * A point in time after RFC 3339, in UTC.
     *
     * @param array<array-key, mixed> $data
     */
    public static function time(array $data, string|int $key, string $path): DateTimeImmutable
    {
        $value = $data[$key] ?? null;

        if (\is_string($value)) {
            foreach (self::TIME_FORMATS as $format) {
                $time = DateTimeImmutable::createFromFormat("!{$format}", $value);
                $errors = DateTimeImmutable::getLastErrors();

                // Warnings mean an overflowing date (February 30th), which PHP would move.
                if ($time !== false && ($errors === false || $errors['warning_count'] === 0)) {
                    return $time->setTimezone(new DateTimeZone('UTC'));
                }
            }
        }

        throw self::invalid($path, $key, 'a time after RFC 3339');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function nullableTime(array $data, string|int $key, string $path): ?DateTimeImmutable
    {
        return ($data[$key] ?? null) === null ? null : self::time($data, $key, $path);
    }

    /**
     * A JSON object; `{}` decodes to an empty array and is one.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function object(array $data, string|int $key, string $path): array
    {
        $value = $data[$key] ?? null;

        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            throw self::invalid($path, $key, 'an object');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<string, mixed>|null
     */
    public static function nullableObject(array $data, string|int $key, string $path): ?array
    {
        return ($data[$key] ?? null) === null ? null : self::object($data, $key, $path);
    }

    /**
     * A list of objects.
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    public static function list(array $data, string|int $key, string $path): array
    {
        $items = [];

        foreach (self::array($data, $key, $path) as $index => $item) {
            $items[] = self::object([$index => $item], $index, self::join($path, $key));
        }

        return $items;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<int>
     */
    public static function ints(array $data, string|int $key, string $path): array
    {
        $items = [];

        foreach (self::array($data, $key, $path) as $index => $item) {
            $items[] = self::int([$index => $item], $index, self::join($path, $key));
        }

        return $items;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<string>
     */
    public static function strings(array $data, string|int $key, string $path): array
    {
        $items = [];

        foreach (self::array($data, $key, $path) as $index => $item) {
            $items[] = self::string([$index => $item], $index, self::join($path, $key));
        }

        return $items;
    }

    /**
     * Labels: an object of strings.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<string, string>
     */
    public static function labels(array $data, string|int $key, string $path): array
    {
        $labels = [];

        foreach (self::object($data, $key, $path) as $name => $value) {
            $labels[(string) $name] = self::string([$name => $value], (string) $name, self::join($path, $key));
        }

        return $labels;
    }

    /**
     * The path of a value below `$path`: `a.b` for keys, `a[3]` for list indexes.
     */
    public static function join(string $path, string|int $key): string
    {
        if (\is_int($key)) {
            return "{$path}[{$key}]";
        }

        return $path === '' ? $key : "{$path}.{$key}";
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<mixed>
     */
    private static function array(array $data, string|int $key, string $path): array
    {
        $value = $data[$key] ?? null;

        if (!\is_array($value) || !array_is_list($value)) {
            throw self::invalid($path, $key, 'a list');
        }

        return $value;
    }

    private static function invalid(string $path, string|int $key, string $expected): SerializationException
    {
        // A list item's path ends with [index]: $path already names its list.
        $field = \is_int($key) ? "{$path}[{$key}]" : self::join($path, $key);

        return new SerializationException("Field {$field} must be {$expected}.");
    }
}
