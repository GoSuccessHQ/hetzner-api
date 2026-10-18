<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner;

/**
 * One page of a list.
 *
 * @template T
 */
final readonly class Page
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public Pagination $pagination,
    ) {}
}
