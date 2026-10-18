<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner;

/**
 * The `meta.pagination` of a list response.
 */
final readonly class Pagination
{
    /**
     * @param int      $page         The page this response holds, from 1.
     * @param int      $perPage      Entries per page (the Cloud API allows up to 50).
     * @param int|null $previousPage Null on the first page.
     * @param int|null $nextPage     Null on the last page.
     * @param int|null $lastPage     Null where the API does not count.
     * @param int|null $totalEntries Null where the API does not count.
     */
    public function __construct(
        public int $page,
        public int $perPage,
        public ?int $previousPage,
        public ?int $nextPage,
        public ?int $lastPage,
        public ?int $totalEntries,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data The decoded list response.
     */
    public static function fromResponse(array $data): self
    {
        $meta = Json::object($data, 'meta', '');
        $pagination = Json::object($meta, 'pagination', 'meta');
        $path = 'meta.pagination';

        return new self(
            Json::int($pagination, 'page', $path),
            Json::int($pagination, 'per_page', $path),
            Json::nullableInt($pagination, 'previous_page', $path),
            Json::nullableInt($pagination, 'next_page', $path),
            Json::nullableInt($pagination, 'last_page', $path),
            Json::nullableInt($pagination, 'total_entries', $path),
        );
    }
}
