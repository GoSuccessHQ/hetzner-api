<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Resource;

use Generator;
use GoSuccess\Hetzner\Exception\NotFoundException;
use GoSuccess\Hetzner\Http\Connection;
use GoSuccess\Hetzner\Http\Method;
use GoSuccess\Hetzner\Json;
use GoSuccess\Hetzner\Page;

/**
 * A resource with a list (`GET /<path>`) and single reads (`GET /<path>/{id}`).
 *
 * @template T
 */
abstract class Listing
{
    /** The path of the list, e.g. `/servers`. */
    protected const string PATH = '';

    /** The key of the items in the list response, e.g. `servers`. */
    protected const string ITEMS = '';

    /** The key of the item in a single response, e.g. `server`. */
    protected const string ITEM = '';

    /**
     * @internal
     */
    final public function __construct(protected readonly Connection $connection) {}

    /**
     * One page of the list.
     *
     * @param int                                    $perPage At most 50.
     * @param array<string, string|int|list<string>> $filters Query parameters of the operation,
     *                                                        e.g. `name`, `label_selector`, `sort`.
     *
     * @return Page<T>
     */
    public function list(int $page = 1, int $perPage = 25, array $filters = []): Page
    {
        return $this->connection->page(static::PATH, static::ITEMS, [...$filters, 'page' => $page, 'per_page' => $perPage], $this->map(...));
    }

    /**
     * Every entry, page by page.
     *
     * @param array<string, string|int|list<string>> $filters
     *
     * @return Generator<int, T>
     */
    public function all(array $filters = []): Generator
    {
        return $this->connection->pages(static::PATH, static::ITEMS, $filters, $this->map(...));
    }

    /**
     * Build an entry from its JSON object.
     *
     * @param array<string, mixed> $data
     *
     * @return T
     */
    abstract protected function map(array $data, string $path): mixed;

    /**
     * One entry; null only when Hetzner answers 404 with `not_found` (a proxy's
     * 404 is no proof that it is gone).
     *
     * @return T|null
     */
    protected function find(int $id): mixed
    {
        try {
            $data = $this->connection->request(Method::Get, static::PATH . "/{$id}");
        } catch (NotFoundException $e) {
            if ($e->errorCode === 'not_found') {
                return null;
            }

            throw $e;
        }

        return $this->map(Json::object($data, static::ITEM, ''), static::ITEM);
    }
}
