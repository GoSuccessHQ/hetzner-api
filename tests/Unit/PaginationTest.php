<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Tests\Unit;

use GoSuccess\Hetzner\ClientOptions;
use GoSuccess\Hetzner\Exception\SerializationException;
use GoSuccess\Hetzner\Http\Connection;
use GoSuccess\Hetzner\Http\Response;
use GoSuccess\Hetzner\Page;
use GoSuccess\Hetzner\Pagination;
use GoSuccess\Hetzner\Tests\Support\MockHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pagination::class)]
#[CoversClass(Page::class)]
#[CoversClass(Connection::class)]
final class PaginationTest extends TestCase
{
    public function testFollowsNextPage(): void
    {
        $http = new MockHttpClient(
            $this->page([1, 2], 1, 2),
            $this->page([3], 2, 3),
            $this->page([4], 3, null),
        );

        $ids = iterator_to_array($this->connection($http)->pages('/servers', 'servers', ['name' => 'a'], static fn(array $item, string $path): string => $path . ':' . json_encode($item['id'])), false);

        self::assertSame(['servers[0]:1', 'servers[1]:2', 'servers[0]:3', 'servers[0]:4'], $ids);
        self::assertSame('https://api.hetzner.cloud/v1/servers?name=a&page=1&per_page=50', $http->requests[0]->uri);
        self::assertSame('https://api.hetzner.cloud/v1/servers?name=a&page=2&per_page=50', $http->requests[1]->uri);
        self::assertSame('https://api.hetzner.cloud/v1/servers?name=a&page=3&per_page=50', $http->requests[2]->uri);
    }

    public function testStopsWhenNextPageDoesNotGrow(): void
    {
        $http = new MockHttpClient($this->page([1], 1, 2), $this->page([2], 2, 1), $this->page([3], 1, 2));

        $items = iterator_to_array($this->connection($http)->pages('/servers', 'servers', [], static fn(array $item): mixed => $item['id']), false);

        self::assertSame([1, 2], $items);
        self::assertSame(2, $http->callCount());
    }

    public function testEndlessNextPageStopsAtTheLimit(): void
    {
        $http = new MockHttpClient($this->page([1], 1, 2), $this->page([2], 2, 3), $this->page([3], 3, 4), $this->page([4], 4, 5));
        $connection = new Connection('https://api.hetzner.cloud/v1', 'token', new ClientOptions(maxPages: 3), $http, static function (float $seconds): void {});

        try {
            iterator_to_array($connection->pages('/servers', 'servers', [], static fn(array $item): mixed => $item['id']), false);
            self::fail('No exception.');
        } catch (SerializationException $e) {
            self::assertStringContainsString('more than 3 pages', $e->getMessage());
        }

        self::assertSame(3, $http->callCount());
    }

    public function testEmptyList(): void
    {
        $http = new MockHttpClient($this->page([], 1, null));

        self::assertSame([], iterator_to_array($this->connection($http)->pages('/servers', 'servers', [], static fn(array $item): mixed => $item), false));
    }

    public function testOnePage(): void
    {
        $http = new MockHttpClient(new Response(200, '{"servers":[{"id":5}],"meta":{"pagination":{"page":2,"per_page":1,"previous_page":1,"next_page":3,"last_page":4,"total_entries":4}}}'));

        $page = $this->connection($http)->page('/servers', 'servers', ['page' => 2, 'per_page' => 1], static fn(array $item): mixed => $item['id']);

        self::assertSame([5], $page->items);
        self::assertEquals(new Pagination(2, 1, 1, 3, 4, 4), $page->pagination);
        self::assertSame('https://api.hetzner.cloud/v1/servers?page=2&per_page=1', $http->requests[0]->uri);
    }

    #[DataProvider('malformedPages')]
    public function testMalformedPageRaises(string $body, string $message): void
    {
        $http = new MockHttpClient(new Response(200, $body));

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage($message);

        iterator_to_array($this->connection($http)->pages('/servers', 'servers', [], static fn(array $item): mixed => $item));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedPages(): iterable
    {
        $meta = '"meta":{"pagination":{"page":1,"per_page":50,"previous_page":null,"next_page":null,"last_page":1,"total_entries":1}}';

        yield 'missing items key' => ['{' . $meta . '}', 'servers'];
        yield 'items not a list' => ['{"servers":{"a":1},' . $meta . '}', 'servers'];
        yield 'item not an object' => ['{"servers":[1],' . $meta . '}', 'servers[0]'];
        yield 'missing meta' => ['{"servers":[]}', 'meta'];
        yield 'page not a number' => ['{"servers":[],"meta":{"pagination":{"page":"1","per_page":50}}}', 'meta.pagination.page'];
    }

    /**
     * @param list<int> $ids
     */
    private function page(array $ids, int $page, ?int $next): Response
    {
        return new Response(200, (string) json_encode([
            'servers' => array_map(static fn(int $id): array => ['id' => $id], $ids),
            'meta' => ['pagination' => ['page' => $page, 'per_page' => 50, 'previous_page' => null, 'next_page' => $next, 'last_page' => 3, 'total_entries' => 4]],
        ]));
    }

    private function connection(MockHttpClient $http): Connection
    {
        return new Connection('https://api.hetzner.cloud/v1', 'token', new ClientOptions(), $http, static function (float $seconds): void {});
    }
}
