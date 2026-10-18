<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Tests\Unit\Http;

use GoSuccess\Hetzner\ClientOptions;
use GoSuccess\Hetzner\Cloud\HetznerCloud;
use GoSuccess\Hetzner\Exception\ApiException;
use GoSuccess\Hetzner\Exception\AuthenticationException;
use GoSuccess\Hetzner\Exception\ConflictException;
use GoSuccess\Hetzner\Exception\NotFoundException;
use GoSuccess\Hetzner\Exception\RateLimitException;
use GoSuccess\Hetzner\Exception\SerializationException;
use GoSuccess\Hetzner\Exception\ServerException;
use GoSuccess\Hetzner\Exception\TransportException;
use GoSuccess\Hetzner\Http\Connection;
use GoSuccess\Hetzner\Http\Method;
use GoSuccess\Hetzner\Http\Response;
use GoSuccess\Hetzner\RateLimit;
use GoSuccess\Hetzner\Tests\Support\ExceptionTrace;
use GoSuccess\Hetzner\Tests\Support\MockHttpClient;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Connection::class)]
#[CoversClass(HetznerCloud::class)]
#[CoversClass(RateLimit::class)]
#[CoversClass(ApiException::class)]
final class ConnectionTest extends TestCase
{
    private const string TOKEN = 'secret-token-0123456789';

    private const float NOW = 1_800_000_000.0;

    /** @var list<float> */
    private array $sleeps = [];

    public function testSendsBearerTokenAndJson(): void
    {
        $http = new MockHttpClient(new Response(201, '{"server":{"id":1}}'));

        $data = $this->connection($http)->request(Method::Post, '/servers', ['dry' => 'x'], ['name' => 'a/b']);

        $request = $http->requests[0];
        self::assertSame(['server' => ['id' => 1]], $data);
        self::assertSame('POST', $request->method->value);
        self::assertSame('https://api.hetzner.cloud/v1/servers?dry=x', $request->uri);
        self::assertSame('Bearer ' . self::TOKEN, $request->headers['Authorization']);
        self::assertSame('application/json', $request->headers['Accept']);
        self::assertSame('application/json', $request->headers['Content-Type']);
        self::assertSame(ClientOptions::DEFAULT_USER_AGENT, $request->headers['User-Agent']);
        self::assertSame('{"name":"a/b"}', $request->body);
        self::assertSame(30.0, $request->timeout);
    }

    public function testEncodesQueryListsAsRepeatedKeys(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'));

        $this->connection($http)->request(Method::Get, '/servers', ['label_selector' => 'env=prod,role in (a b)', 'sort' => ['name:asc', 'id'], 'page' => 2]);

        self::assertSame('https://api.hetzner.cloud/v1/servers?label_selector=env%3Dprod%2Crole%20in%20%28a%20b%29&sort=name%3Aasc&sort=id&page=2', $http->requests[0]->uri);
        self::assertNull($http->requests[0]->body);
        self::assertArrayNotHasKey('Content-Type', $http->requests[0]->headers);
    }

    public function testEmptyLabelsAreEncodedAsObject(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'));

        $this->connection($http)->request(Method::Put, '/servers/1', body: ['labels' => (object) []]);

        self::assertSame('{"labels":{}}', $http->requests[0]->body);
    }

    public function testEmptySuccessBodyIsAnEmptyObject(): void
    {
        $http = new MockHttpClient(new Response(204, ''));

        self::assertSame([], $this->connection($http)->request(Method::Delete, '/firewalls/1'));
    }

    public function testRetriesGetOn5xxAndNetworkFailure(): void
    {
        $http = new MockHttpClient(new Response(503, 'busy'), new TransportException('reset'), new Response(200, '{"ok":true}'));

        self::assertSame(['ok' => true], $this->connection($http)->request(Method::Get, '/servers'));
        self::assertSame(3, $http->callCount());
        self::assertSame([0.5, 1.0], $this->sleeps);
    }

    #[DataProvider('idempotentMethods')]
    public function testRetriesIdempotentMethods(Method $method): void
    {
        $http = new MockHttpClient(new Response(500, ''), new Response(200, '{}'));

        $this->connection($http)->request($method, '/servers/1');

        self::assertSame(2, $http->callCount());
    }

    /**
     * @return iterable<string, array{Method}>
     */
    public static function idempotentMethods(): iterable
    {
        yield 'GET' => [Method::Get];
        yield 'PUT' => [Method::Put];
        yield 'DELETE' => [Method::Delete];
    }

    public function testNeverRetriesPostAfterAServerError(): void
    {
        $http = new MockHttpClient(new Response(500, '{"error":{"code":"server_error","message":"boom"}}'), new Response(201, '{}'));

        try {
            $this->connection($http)->request(Method::Post, '/servers', body: ['name' => 'x']);
            self::fail('No exception.');
        } catch (ServerException $e) {
            self::assertSame('server_error', $e->errorCode);
        }

        self::assertSame(1, $http->callCount());
    }

    public function testNeverRetriesPostAfterANetworkFailure(): void
    {
        $http = new MockHttpClient(new TransportException('timeout'), new Response(201, '{}'));

        $this->expectException(TransportException::class);

        try {
            $this->connection($http)->request(Method::Post, '/servers', body: ['name' => 'x']);
        } finally {
            self::assertSame(1, $http->callCount());
        }
    }

    public function testRetriesRateLimitUntilReset(): void
    {
        $http = new MockHttpClient(
            new Response(429, '{"error":{"code":"rate_limit_exceeded","message":"limit"}}', $this->rateHeaders(0, 3)),
            new Response(201, '{}'),
        );

        $this->connection($http)->request(Method::Post, '/servers', body: ['name' => 'x']);

        self::assertSame(2, $http->callCount());
        self::assertSame([3.0], $this->sleeps);
    }

    public function testRateLimitWaitIsCappedAndAtLeastTheBaseDelay(): void
    {
        $http = new MockHttpClient(
            new Response(429, '', $this->rateHeaders(0, 3600)),
            new Response(429, '', $this->rateHeaders(0, -5)),
            new Response(200, '{}'),
        );

        $this->connection($http)->request(Method::Get, '/servers');

        self::assertSame([10.0, 0.5], $this->sleeps);
    }

    public function testRateLimitWithoutRetryThrowsAtOnce(): void
    {
        $http = new MockHttpClient(new Response(429, '{"error":{"code":"rate_limit_exceeded","message":"limit"}}', $this->rateHeaders(0, 7)));

        try {
            $this->connection($http, new ClientOptions(retryOnRateLimit: false))->request(Method::Get, '/servers');
            self::fail('No exception.');
        } catch (RateLimitException $e) {
            self::assertSame(7, $e->retryAfter);
            self::assertSame('rate_limit_exceeded', $e->errorCode);
        }

        self::assertSame(1, $http->callCount());
        self::assertSame([], $this->sleeps);
    }

    public function testReadsRateLimitHeaders(): void
    {
        $http = new MockHttpClient(new Response(200, '{}', $this->rateHeaders(3599, 1)), new Response(200, '{}', ['ratelimit-limit' => '3600']));
        $connection = $this->connection($http);

        self::assertNull($this->connection(new MockHttpClient())->lastRateLimit());

        $connection->request(Method::Get, '/servers');
        $limit = $connection->lastRateLimit();

        self::assertNotNull($limit);
        self::assertSame(3600, $limit->limit);
        self::assertSame(3599, $limit->remaining);
        self::assertSame((int) self::NOW + 1, $limit->reset->getTimestamp());

        // An answer without the three headers keeps the last known state.
        $connection->request(Method::Get, '/servers');
        self::assertSame($limit, $connection->lastRateLimit());
    }

    /**
     * @param class-string<ApiException> $class
     */
    #[DataProvider('errorEnvelopes')]
    public function testErrorEnvelopeBecomesApiExceptionWithCode(int $status, string $body, string $class, string $code): void
    {
        $http = new MockHttpClient(new Response($status, $body));

        try {
            $this->connection($http, new ClientOptions(maxRetries: 0))->request(Method::Get, '/servers/1');
            self::fail('No exception.');
        } catch (ApiException $e) {
            self::assertInstanceOf($class, $e);
            self::assertSame($code, $e->errorCode);
            self::assertSame($status, $e->statusCode);
        }
    }

    /**
     * @return iterable<string, array{int, string, class-string<ApiException>, string}>
     */
    public static function errorEnvelopes(): iterable
    {
        yield 'not found' => [404, '{"error":{"code":"not_found","message":"server not found","details":{}}}', NotFoundException::class, 'not_found'];
        yield 'uniqueness' => [409, '{"error":{"code":"uniqueness_error","message":"name is already used","details":{"fields":[{"name":"name"}]}}}', ConflictException::class, 'uniqueness_error'];
        yield 'locked' => [423, '{"error":{"code":"locked","message":"locked","details":{}}}', ApiException::class, 'locked'];
        yield 'unauthorized' => [401, '{"error":{"code":"unauthorized","message":"unable to authenticate","details":{}}}', AuthenticationException::class, 'unauthorized'];
    }

    public function testInvalidInputCarriesTheFields(): void
    {
        $body = '{"error":{"code":"invalid_input","message":"invalid input in fields \'name\', \'server_type\'","details":{"fields":[{"name":"name","messages":["Missing data for required field."]},{"name":"server_type","messages":["must be a string","too long"]},{"name":3},"x"]}}}';
        $http = new MockHttpClient(new Response(400, $body));

        try {
            $this->connection($http)->request(Method::Post, '/servers', body: []);
            self::fail('No exception.');
        } catch (ApiException $e) {
            self::assertSame('invalid_input', $e->errorCode);
            self::assertSame(['name' => ['Missing data for required field.'], 'server_type' => ['must be a string', 'too long']], $e->fields);
        }
    }

    public function testErrorWithoutEnvelopeHasNoCode(): void
    {
        $http = new MockHttpClient(new Response(404, '<html>Not Found</html>'));

        try {
            $this->connection($http)->request(Method::Get, '/servers/1');
            self::fail('No exception.');
        } catch (NotFoundException $e) {
            self::assertNull($e->errorCode);
            self::assertSame([], $e->fields);
        }
    }

    public function testARedirectIsAnError(): void
    {
        $http = new MockHttpClient(new Response(302, '', ['location' => 'https://elsewhere.test/']));

        $this->expectException(ApiException::class);

        $this->connection($http)->request(Method::Get, '/servers');
    }

    #[DataProvider('notJsonObjects')]
    public function testNonJsonSuccessIsSerializationError(string $body): void
    {
        $http = new MockHttpClient(new Response(200, $body));

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('GET /servers');

        $this->connection($http)->request(Method::Get, '/servers');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notJsonObjects(): iterable
    {
        yield 'html' => ['<html>ok</html>'];
        yield 'list' => ['[1,2]'];
        yield 'scalar' => ['"servers"'];
    }

    public function testBodyThatCannotBeEncodedIsASerializationError(): void
    {
        $http = new MockHttpClient();

        $this->expectException(SerializationException::class);

        $this->connection($http)->request(Method::Post, '/servers', body: ['name' => "\xB1"]);
    }

    #[DataProvider('invalidTokens')]
    public function testRejectsAnInvalidToken(string $token): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Connection('https://api.hetzner.cloud/v1', $token, new ClientOptions(), new MockHttpClient());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTokens(): iterable
    {
        yield 'empty' => [''];
        yield 'line break' => ["token\r\nX-Evil: 1"];
        yield 'space' => ['token with space'];
    }

    public function testTokenNeverInMessagesDebugInfoOrTraces(): void
    {
        $http = new MockHttpClient(new Response(401, '{"error":{"code":"unauthorized","message":"unable to authenticate"}}'));
        $cloud = new HetznerCloud(self::TOKEN, new ClientOptions(maxRetries: 0), $http);
        $connection = $this->connection($http);

        $e = ExceptionTrace::capture(fn(): array => $this->connection($http)->request(Method::Get, '/servers'));

        self::assertStringNotContainsString(self::TOKEN, $e->getMessage());
        self::assertStringNotContainsString(self::TOKEN, ExceptionTrace::arguments($e));
        self::assertStringNotContainsString(self::TOKEN, print_r($cloud, true));
        self::assertStringNotContainsString(self::TOKEN, print_r($connection, true));
    }

    public function testCloudExposesTheLastRateLimit(): void
    {
        $cloud = new HetznerCloud(self::TOKEN, httpClient: new MockHttpClient());

        self::assertNull($cloud->rateLimit());
    }

    private function connection(MockHttpClient $http, ClientOptions $options = new ClientOptions()): Connection
    {
        return new Connection(
            'https://api.hetzner.cloud/v1',
            self::TOKEN,
            $options,
            $http,
            function (float $seconds): void {
                $this->sleeps[] = $seconds;
            },
            static fn(): float => self::NOW,
        );
    }

    /**
     * @return array<string, string>
     */
    private function rateHeaders(int $remaining, int $resetIn): array
    {
        return ['ratelimit-limit' => '3600', 'ratelimit-remaining' => (string) $remaining, 'ratelimit-reset' => (string) ((int) self::NOW + $resetIn)];
    }
}
