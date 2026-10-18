<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner;

use InvalidArgumentException;

/**
 * Immutable transport and retry settings.
 *
 * The token is not part of the options: the client receives it separately, so
 * options can be shared and logged safely.
 */
final readonly class ClientOptions
{
    public const string DEFAULT_USER_AGENT = 'gosuccess/hetzner-api (+https://github.com/GoSuccessHQ/hetzner-api)';

    /**
     * The two timeouts travel with every request (see {@see Http\Request}), so they
     * also apply through a custom transport and take precedence over the defaults
     * the transport was created with.
     *
     * @param float  $timeout        Maximum duration of a request in seconds; `0.0` means
     *                               no limit.
     * @param float  $connectTimeout Maximum duration of the connection phase in seconds.
     * @param int    $maxRetries       How often a request is retried after a network
     *                                 failure or a server error (5xx). Only `GET`, `PUT`
     *                                 and `DELETE`; a `POST` never, since a repeated
     *                                 `POST /servers` orders a second server.
     * @param float  $retryBaseDelay   Base delay in seconds of the exponential backoff.
     * @param float  $maxRetryDelay    Upper bound in seconds for a single wait.
     * @param string $userAgent        Value of the `User-Agent` header.
     * @param bool   $retryOnRateLimit Wait for `RateLimit-Reset` (at most `maxRetryDelay`)
     *                                 and retry a `429`, for every method, since Hetzner did
     *                                 not run the request. Off, a `429` throws at once.
     * @param int    $maxPages         Upper bound of pages when walking a whole list (50 entries
     *                                 each), so a wrong `next_page` never makes an endless walk.
     */
    public function __construct(
        public float $timeout = 30.0,
        public float $connectTimeout = 10.0,
        public int $maxRetries = 2,
        public float $retryBaseDelay = 0.5,
        public float $maxRetryDelay = 10.0,
        public string $userAgent = self::DEFAULT_USER_AGENT,
        public bool $retryOnRateLimit = true,
        public int $maxPages = 100,
    ) {
        foreach (['timeout' => $timeout, 'connectTimeout' => $connectTimeout, 'retryBaseDelay' => $retryBaseDelay, 'maxRetryDelay' => $maxRetryDelay] as $name => $value) {
            // NAN and INF would turn into warnings or endless waits once converted for cURL or sleep().
            if (!is_finite($value) || $value < 0.0) {
                throw new InvalidArgumentException("{$name} must be a finite number that is not negative.");
            }
        }

        if ($maxPages < 1) {
            throw new InvalidArgumentException('maxPages must be at least 1.');
        }

        if ($maxRetries < 0) {
            throw new InvalidArgumentException('maxRetries must not be negative.');
        }

        if (trim($userAgent) === '') {
            throw new InvalidArgumentException('userAgent must not be empty.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $userAgent) === 1) {
            throw new InvalidArgumentException('userAgent must not contain control characters.');
        }
    }
}
