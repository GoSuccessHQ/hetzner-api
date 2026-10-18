<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Tests\Unit;

use DateTimeImmutable;
use GoSuccess\Hetzner\Exception\SerializationException;
use GoSuccess\Hetzner\Json;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Json::class)]
final class JsonTest extends TestCase
{
    public function testReadsTypedValues(): void
    {
        $data = ['name' => 'cx22', 'id' => 7, 'memory' => 4, 'disk' => 0.5, 'on' => false, 'none' => null, 'list' => [1, 2], 'names' => ['a', 'b'], 'object' => ['a' => 1], 'items' => [['a' => 1], []]];

        self::assertSame('cx22', Json::string($data, 'name', 'x'));
        self::assertSame(7, Json::int($data, 'id', 'x'));
        self::assertSame(4.0, Json::float($data, 'memory', 'x'));
        self::assertSame(0.5, Json::float($data, 'disk', 'x'));
        self::assertFalse(Json::bool($data, 'on', 'x'));
        self::assertNull(Json::nullableString($data, 'none', 'x'));
        self::assertNull(Json::nullableString($data, 'missing', 'x'));
        self::assertNull(Json::nullableInt($data, 'none', 'x'));
        self::assertSame(7, Json::nullableInt($data, 'id', 'x'));
        self::assertSame([1, 2], Json::ints($data, 'list', 'x'));
        self::assertSame(['a', 'b'], Json::strings($data, 'names', 'x'));
        self::assertSame(['a' => 1], Json::object($data, 'object', 'x'));
        self::assertNull(Json::nullableObject($data, 'none', 'x'));
        self::assertSame([['a' => 1], []], Json::list($data, 'items', 'x'));
    }

    public function testReadsLabels(): void
    {
        self::assertSame(['env' => 'prod', 'empty' => ''], Json::labels(['labels' => ['env' => 'prod', 'empty' => '']], 'labels', 'server'));
        self::assertSame([], Json::labels(['labels' => []], 'labels', 'server'));
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('wrongValues')]
    public function testWrongValueNamesItsPath(callable $read, array $data, string $path): void
    {
        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage($path);

        $read($data);
    }

    /**
     * @return iterable<string, array{callable, array<string, mixed>, string}>
     */
    public static function wrongValues(): iterable
    {
        yield 'missing string' => [static fn(array $d): string => Json::string($d, 'name', 'servers[3].server_type'), [], 'servers[3].server_type.name'];
        yield 'number as string' => [static fn(array $d): string => Json::string($d, 'name', 'server'), ['name' => 1], 'server.name'];
        yield 'string as int' => [static fn(array $d): int => Json::int($d, 'id', 'server'), ['id' => '1'], 'server.id'];
        yield 'float as int' => [static fn(array $d): int => Json::int($d, 'id', 'server'), ['id' => 1.5], 'server.id'];
        yield 'string as bool' => [static fn(array $d): bool => Json::bool($d, 'on', 'server'), ['on' => 'true'], 'server.on'];
        yield 'list as object' => [static fn(array $d): array => Json::object($d, 'o', 'server'), ['o' => [1]], 'server.o'];
        yield 'object as list' => [static fn(array $d): array => Json::list($d, 'l', 'server'), ['l' => ['a' => []]], 'server.l'];
        yield 'list item not an object' => [static fn(array $d): array => Json::list($d, 'l', 'server'), ['l' => [[], 2]], 'server.l[1]'];
        yield 'label not a string' => [static fn(array $d): array => Json::labels($d, 'labels', 'server'), ['labels' => ['a' => 1]], 'server.labels.a'];
        yield 'labels a list' => [static fn(array $d): array => Json::labels($d, 'labels', 'server'), ['labels' => ['a']], 'server.labels'];
        yield 'ints with string' => [static fn(array $d): array => Json::ints($d, 'l', 'server'), ['l' => [1, '2']], 'server.l[1]'];
        yield 'bad time' => [static fn(array $d): DateTimeImmutable => Json::time($d, 'created', 'server'), ['created' => 'yesterday'], 'server.created'];
        yield 'overflowing time' => [static fn(array $d): DateTimeImmutable => Json::time($d, 'created', 'server'), ['created' => '2026-02-30T10:00:00+00:00'], 'server.created'];
    }

    #[DataProvider('times')]
    public function testParsesTimesToUtc(string $value, string $expected): void
    {
        $time = Json::time(['t' => $value], 't', 'x');

        self::assertSame($expected, $time->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('UTC', $time->getTimezone()->getName());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function times(): iterable
    {
        yield 'offset' => ['2016-01-30T23:55:00+00:00', '2016-01-30T23:55:00.000000+00:00'];
        yield 'zulu' => ['2016-01-30T23:55:00Z', '2016-01-30T23:55:00.000000+00:00'];
        yield 'other offset' => ['2016-01-31T00:55:00+01:00', '2016-01-30T23:55:00.000000+00:00'];
        yield 'fraction' => ['2016-01-30T23:55:00.123456+00:00', '2016-01-30T23:55:00.123456+00:00'];
        yield 'fraction zulu' => ['2016-01-30T23:55:00.5Z', '2016-01-30T23:55:00.500000+00:00'];
        yield 'nanoseconds' => ['2016-01-30T23:55:00.123456789Z', '2016-01-30T23:55:00.123456+00:00'];
    }

    public function testNullableTime(): void
    {
        self::assertNull(Json::nullableTime(['t' => null], 't', 'x'));
        self::assertNull(Json::nullableTime([], 't', 'x'));
        self::assertNotNull(Json::nullableTime(['t' => '2016-01-30T23:55:00Z'], 't', 'x'));
    }
}
