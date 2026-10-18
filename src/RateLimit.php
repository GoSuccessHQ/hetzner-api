<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner;

use DateTimeImmutable;
use GoSuccess\Hetzner\Http\Response;

/**
 * The rate limit as the last response reported it: requests per hour, what is
 * left, and when the full number is available again. The Cloud API refills one
 * request per second (3600 per hour by default, per project).
 */
final readonly class RateLimit
{
    public function __construct(
        public int $limit,
        public int $remaining,
        public DateTimeImmutable $reset,
    ) {}

    /**
     * The state from `RateLimit-Limit`, `-Remaining` and `-Reset`; null unless all
     * three are whole numbers.
     *
     * @internal
     */
    public static function fromResponse(Response $response): ?self
    {
        $values = [];

        foreach (['ratelimit-limit', 'ratelimit-remaining', 'ratelimit-reset'] as $name) {
            $value = trim($response->header($name));

            if (preg_match('/^\d{1,12}$/D', $value) !== 1) {
                return null;
            }

            $values[] = (int) $value;
        }

        return new self($values[0], $values[1], new DateTimeImmutable("@{$values[2]}"));
    }
}
