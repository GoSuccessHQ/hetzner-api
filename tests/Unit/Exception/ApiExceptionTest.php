<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Tests\Unit\Exception;

use GoSuccess\Hetzner\Exception\ApiException;
use GoSuccess\Hetzner\Exception\AuthenticationException;
use GoSuccess\Hetzner\Exception\ErrorDetails;
use GoSuccess\Hetzner\Exception\HetznerException;
use GoSuccess\Hetzner\Exception\NotFoundException;
use GoSuccess\Hetzner\Exception\RateLimitException;
use GoSuccess\Hetzner\Http\Method;
use GoSuccess\Hetzner\Http\Request;
use GoSuccess\Hetzner\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiException::class)]
#[CoversClass(ErrorDetails::class)]
#[CoversClass(RateLimitException::class)]
final class ApiExceptionTest extends TestCase
{
    #[DataProvider('errorBodies')]
    public function testNormalizesTheErrorBodies(string $body, string $message, ?string $errorCode): void
    {
        $exception = ApiException::fromResponse(new Response(400, $body), $this->request());

        self::assertSame($message, $exception->getMessage());
        self::assertSame($errorCode, $exception->errorCode);
        self::assertSame($body, $exception->responseBody);
    }

    /**
     * @return iterable<string, array{string, string, string|null}>
     */
    public static function errorBodies(): iterable
    {
        $prefix = 'POST https://api.hetzner.cloud/v1/servers failed with HTTP 400';

        // The error envelope of the Cloud API (specification, 2026-10-18).
        yield 'envelope' => ['{"error":{"code":"invalid_input","message":"invalid input in field \'server_type\'","details":{"fields":[{"name":"server_type","messages":["Missing data for required field."]}]}}}', "{$prefix}: invalid input in field 'server_type' (invalid_input)", 'invalid_input'];
        yield 'envelope without message' => ['{"error":{"code":"locked","details":null}}', "{$prefix} (locked)", 'locked'];
        yield 'envelope without code' => ['{"error":{"message":"Something"}}', "{$prefix}: Something", null];

        // Defensive fallbacks.
        yield 'envelope without strings' => ['{"error":{"code":1,"message":2}}', $prefix, null];
        yield 'error not an object' => ['{"error":"x"}', $prefix, null];
        yield 'other json' => ['{"message":"x"}', $prefix, null];
        yield 'plain text' => ['Bad Gateway', "{$prefix}: Bad Gateway", null];
        yield 'json list' => ['["x"]', $prefix, null];
        yield 'empty' => ['', $prefix, null];
    }

    public function testIncludesTheReasonPhraseButNotTheQuery(): void
    {
        $exception = ApiException::fromResponse(
            new Response(404, '{"error":{"code":"not_found","message":"server with ID \'1\' not found","details":{}}}', [], 'Not Found'),
            new Request(Method::Get, 'https://api.hetzner.cloud/v1/servers/1?label_selector=secret'),
        );

        self::assertInstanceOf(NotFoundException::class, $exception);
        self::assertInstanceOf(HetznerException::class, $exception);
        self::assertSame("GET https://api.hetzner.cloud/v1/servers/1 failed with HTTP 404 Not Found: server with ID '1' not found (not_found)", $exception->getMessage());
    }

    public function testMapsAnInvalidKeyToAuthenticationException(): void
    {
        $exception = ApiException::fromResponse(new Response(401, 'Unauthorized'), $this->request());

        self::assertInstanceOf(AuthenticationException::class, $exception);
        self::assertSame(401, $exception->statusCode);
    }

    public function testShortensHugeBodiesWithoutBreakingUtf8(): void
    {
        // One ASCII byte first, so that the cut after 500 bytes splits an "ä".
        $body = 'a' . str_repeat('ä', 400);
        $exception = ApiException::fromResponse(new Response(502, $body), $this->request());

        self::assertTrue(mb_check_encoding($exception->getMessage(), 'UTF-8'));
        self::assertStringEndsWith('…', $exception->getMessage());
        // The prefix (method, URL, status) plus at most 500 bytes of the body and the ellipsis.
        self::assertLessThan(650, \strlen($exception->getMessage()));
        self::assertSame($body, $exception->responseBody);
    }

    public function testDecodesTheBody(): void
    {
        $exception = ApiException::fromResponse(new Response(400, '{"error":{"code":"locked"}}'), $this->request());

        self::assertSame(['error' => ['code' => 'locked']], $exception->decodedBody());
        self::assertNull(ApiException::fromResponse(new Response(502), $this->request())->decodedBody());
    }

    public function testParsesRetryAfter(): void
    {
        $now = 1_800_000_000.0;

        self::assertSame(30, RateLimitException::parseRetryAfter('30', $now));
        self::assertSame(0, RateLimitException::parseRetryAfter('Wed, 21 Oct 2015 07:28:00 GMT', $now));
        self::assertSame(120, RateLimitException::parseRetryAfter(gmdate('D, d M Y H:i:s', 1_800_000_120) . ' GMT', $now));
        // The obsolete RFC 850 and asctime() formats of RFC 9110.
        self::assertSame(120, RateLimitException::parseRetryAfter(gmdate('l, d-M-y H:i:s', 1_800_000_120) . ' GMT', $now));
        self::assertSame(3600, RateLimitException::parseRetryAfter('Fri Jan 15 09:00:00 2027', (float) gmmktime(8, 0, 0, 1, 15, 2027)));
        self::assertSame(3600, RateLimitException::parseRetryAfter('Wed Jan  6 09:00:00 2027', (float) gmmktime(8, 0, 0, 1, 6, 2027)));

        $future = gmdate('D, d M Y H:i:s', time() + 120) . ' GMT';
        self::assertGreaterThanOrEqual(118, RateLimitException::parseRetryAfter($future));
    }

    #[DataProvider('invalidRetryAfterValues')]
    public function testRejectsRetryAfterValuesOfNeitherForm(string $value): void
    {
        self::assertNull(RateLimitException::parseRetryAfter($value, 1_800_000_000.0));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRetryAfterValues(): iterable
    {
        yield 'empty' => [''];
        yield 'word' => ['soon'];
        // strtotime() read these as times of day, relative dates or zone offsets.
        yield 'fraction' => ['1.5'];
        yield 'fraction read as a time of day' => ['10.0'];
        yield 'negative' => ['-1'];
        yield 'signed' => ['+5'];
        yield 'military zone' => ['x'];
        yield 'now' => ['now'];
        yield 'relative' => ['tomorrow'];
        yield 'weekday' => ['Sun'];
        yield 'unit' => ['30s'];
        yield 'weekday that does not match' => ['Mon, 21 Oct 2015 07:28:00 GMT'];
        yield 'day beyond the month' => ['Thu, 32 Oct 2015 07:28:00 GMT'];
        yield 'other zone' => ['Wed, 21 Oct 2015 07:28:00 CET'];
        yield 'lower case' => ['wed, 21 oct 2015 07:28:00 GMT'];
        yield 'ISO 8601' => ['2015-10-21T07:28:00Z'];
    }

    public function testIgnoresAnInvalidRetryAfter(): void
    {
        $exception = ApiException::fromResponse(new Response(429, '', ['retry-after' => '1.5']), $this->request(), 1_800_000_000.0);

        self::assertInstanceOf(RateLimitException::class, $exception);
        self::assertNull($exception->retryAfter);
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('rateLimitHeaders')]
    public function testRateLimitExceptionCarriesTheAnnouncedWait(array $headers, ?int $retryAfter): void
    {
        $exception = ApiException::fromResponse(new Response(429, '', $headers), $this->request(), 1_800_000_000.0);

        self::assertInstanceOf(RateLimitException::class, $exception);
        self::assertSame($retryAfter, $exception->retryAfter);
    }

    /**
     * @return iterable<string, array{array<string, string>, int|null}>
     */
    public static function rateLimitHeaders(): iterable
    {
        yield 'seconds' => [['retry-after' => '7'], 7];
        yield 'date' => [['retry-after' => 'Fri, 15 Jan 2027 08:00:12 GMT'], 12];
        yield 'date in the past' => [['retry-after' => 'Wed, 21 Oct 2015 07:28:00 GMT'], 0];
        yield 'nothing' => [[], null];
    }

    private function request(): Request
    {
        return new Request(Method::Post, 'https://api.hetzner.cloud/v1/servers');
    }
}
