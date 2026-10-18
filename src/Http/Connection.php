<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Http;

use Closure;
use Generator;
use GoSuccess\Hetzner\ClientOptions;
use GoSuccess\Hetzner\Exception\ApiException;
use GoSuccess\Hetzner\Exception\RateLimitException;
use GoSuccess\Hetzner\Exception\SerializationException;
use GoSuccess\Hetzner\Exception\TransportException;
use GoSuccess\Hetzner\Json;
use GoSuccess\Hetzner\Page;
use GoSuccess\Hetzner\Pagination;
use GoSuccess\Hetzner\RateLimit;
use InvalidArgumentException;
use JsonException;
use SensitiveParameter;

/**
 * JSON requests to one Hetzner API with the token, retries and the rate limit.
 *
 * Network failures and server errors (5xx) are retried with exponential
 * backoff for `GET`, `PUT` and `DELETE`, never for `POST`: a repeated
 * `POST /servers` orders a second server. A `429` is retried for every method
 * (the API did not run the request) after waiting for `RateLimit-Reset`.
 * Every other status outside 2xx, also a redirect, throws an {@see ApiException}.
 *
 * @internal
 */
final class Connection
{
    /** Entries per page when walking a whole list; the maximum of the Cloud API. */
    public const int PER_PAGE = 50;

    private readonly string $baseUrl;

    private readonly Closure $sleep;

    private readonly Closure $now;

    private ?RateLimit $rateLimit = null;

    /**
     * @param (Closure(float): void)|null $sleep Waits the given seconds; replaceable for tests.
     * @param (Closure(): float)|null     $now   The current Unix time; replaceable for tests.
     */
    public function __construct(
        string $baseUrl,
        #[SensitiveParameter]
        private readonly string $token,
        private readonly ClientOptions $options,
        private readonly HttpClient $httpClient,
        ?Closure $sleep = null,
        ?Closure $now = null,
    ) {
        // Printable ASCII without spaces, so the token cannot break the Authorization header.
        if (preg_match('/^[\x21-\x7E]+$/D', $token) !== 1) {
            throw new InvalidArgumentException('The API token must be printable ASCII without spaces.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->sleep = $sleep ?? static function (float $seconds): void {
            usleep((int) ($seconds * 1_000_000));
        };
        $this->now = $now ?? static fn(): float => microtime(true);
    }

    /**
     * Send a request and return the decoded JSON object (`[]` for an empty body).
     *
     * @param array<string, string|int|list<string>> $query Lists are sent as repeated keys.
     * @param array<string, mixed>|null              $body  Encoded as JSON; use `(object) []`
     *                                                      for an empty object.
     *
     * @return array<string, mixed>
     *
     * @throws ApiException          for a status outside 2xx after the retries
     * @throws TransportException    for a network failure after the retries
     * @throws SerializationException if the body cannot be encoded or the answer is no JSON object
     */
    public function request(Method $method, string $path, array $query = [], #[SensitiveParameter] ?array $body = null): array
    {
        $headers = [
            'Authorization' => "Bearer {$this->token}",
            'Accept' => 'application/json',
            'User-Agent' => $this->options->userAgent,
        ];
        $json = null;

        if ($body !== null) {
            try {
                $json = json_encode($body, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
            } catch (JsonException) {
                throw new SerializationException("The body of {$method->value} {$path} cannot be encoded as JSON.");
            }

            $headers['Content-Type'] = 'application/json';
        }

        $request = new Request(
            method: $method,
            uri: $this->baseUrl . '/' . ltrim($path, '/') . self::query($query),
            headers: $headers,
            body: $json,
            timeout: $this->options->timeout,
            connectTimeout: $this->options->connectTimeout,
        );

        $response = $this->send($request);

        if (!$response->isSuccessful) {
            throw ApiException::fromResponse($response, $request, ($this->now)());
        }

        if (trim($response->body) === '') {
            return [];
        }

        try {
            $decoded = json_decode($response->body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $decoded = null;
        }

        if (!\is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new SerializationException("The answer to {$method->value} {$path} (HTTP {$response->statusCode}) is not a JSON object.");
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * One page of a list.
     *
     * @template T
     *
     * @param array<string, string|int|list<string>>        $query
     * @param Closure(array<string, mixed>, string): T $map Gets each item and its path (`servers[3]`).
     *
     * @return Page<T>
     */
    public function page(string $path, string $itemsKey, array $query, Closure $map): Page
    {
        $data = $this->request(Method::Get, $path, $query);
        $items = [];

        foreach (Json::list($data, $itemsKey, '') as $index => $item) {
            $items[] = $map($item, "{$itemsKey}[{$index}]");
        }

        return new Page($items, Pagination::fromResponse($data));
    }

    /**
     * Every entry of a list, page by page with {@see PER_PAGE} entries; ends when
     * `next_page` is null or does not grow.
     *
     * @template T
     *
     * @param array<string, string|int|list<string>>        $query
     * @param Closure(array<string, mixed>, string): T $map
     *
     * @return Generator<int, T>
     */
    public function pages(string $path, string $itemsKey, array $query, Closure $map): Generator
    {
        $page = 1;

        while (true) {
            $result = $this->page($path, $itemsKey, [...$query, 'page' => $page, 'per_page' => self::PER_PAGE], $map);

            yield from $result->items;

            $next = $result->pagination->nextPage;

            if ($next === null || $next <= $page) {
                return;
            }

            $page = $next;
        }
    }

    /**
     * The rate limit of the last response that reported it.
     */
    public function lastRateLimit(): ?RateLimit
    {
        return $this->rateLimit;
    }

    /**
     * Wait the given seconds with the connection's sleep function.
     */
    public function sleep(float $seconds): void
    {
        ($this->sleep)($seconds);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->baseUrl, 'token' => '********', 'options' => $this->options];
    }

    private function send(#[SensitiveParameter] Request $request): Response
    {
        for ($attempt = 0; ; $attempt++) {
            $retriesLeft = $attempt < $this->options->maxRetries;

            try {
                $response = $this->httpClient->send($request);
            } catch (TransportException $exception) {
                if (!$retriesLeft || !$request->method->isIdempotent()) {
                    throw $exception;
                }

                $this->sleep($this->backoff($attempt));

                continue;
            }

            $this->rateLimit = RateLimit::fromResponse($response) ?? $this->rateLimit;

            if ($response->statusCode === 429 && $retriesLeft && $this->options->retryOnRateLimit) {
                $announced = RateLimitException::announcedDelay($response, ($this->now)()) ?? 0.0;
                $this->sleep(min($this->options->maxRetryDelay, max($this->options->retryBaseDelay, $announced)));

                continue;
            }

            if ($response->statusCode >= 500 && $retriesLeft && $request->method->isIdempotent()) {
                $this->sleep($this->backoff($attempt));

                continue;
            }

            return $response;
        }
    }

    private function backoff(int $attempt): float
    {
        return min($this->options->maxRetryDelay, $this->options->retryBaseDelay * (2 ** $attempt));
    }

    /**
     * @param array<string, string|int|list<string>> $query
     */
    private static function query(array $query): string
    {
        $pairs = [];

        foreach ($query as $name => $value) {
            foreach (\is_array($value) ? $value : [$value] as $item) {
                $pairs[] = rawurlencode($name) . '=' . rawurlencode((string) $item);
            }
        }

        return $pairs === [] ? '' : '?' . implode('&', $pairs);
    }
}
