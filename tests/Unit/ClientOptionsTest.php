<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Tests\Unit;

use GoSuccess\Hetzner\ClientOptions;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClientOptions::class)]
final class ClientOptionsTest extends TestCase
{
    public function testHasSensibleDefaults(): void
    {
        $options = new ClientOptions();

        self::assertSame(30.0, $options->timeout);
        self::assertSame(2, $options->maxRetries);
        self::assertSame(0.5, $options->retryBaseDelay);
        self::assertTrue($options->retryOnRateLimit);
        self::assertSame(100, $options->maxPages);
        self::assertSame(10.0, $options->maxRetryDelay);
        self::assertSame('gosuccess/hetzner-api (+https://github.com/GoSuccessHQ/hetzner-api)', $options->userAgent);
    }

    /**
     * @param array{timeout?: float, connectTimeout?: float, maxRetries?: int, retryBaseDelay?: float, maxRetryDelay?: float, userAgent?: string, maxPages?: int} $arguments
     */
    #[DataProvider('invalidOptions')]
    public function testRejectsInvalidValues(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ClientOptions(...$arguments);
    }

    /**
     * @return iterable<string, array{array{timeout?: float, connectTimeout?: float, maxRetries?: int, retryBaseDelay?: float, maxRetryDelay?: float, userAgent?: string, maxPages?: int}}>
     */
    public static function invalidOptions(): iterable
    {
        yield 'negative timeout' => [['timeout' => -1.0]];
        yield 'endless timeout' => [['timeout' => \INF]];
        yield 'negative connect timeout' => [['connectTimeout' => -1.0]];
        yield 'negative retries' => [['maxRetries' => -1]];
        yield 'no pages' => [['maxPages' => 0]];
        yield 'negative base delay' => [['retryBaseDelay' => -0.5]];
        yield 'undefined base delay' => [['retryBaseDelay' => \NAN]];
        yield 'negative max delay' => [['maxRetryDelay' => -0.5]];
        yield 'empty user agent' => [['userAgent' => ' ']];
        yield 'user agent with line break' => [['userAgent' => "agent\r\nX-Evil: 1"]];
    }
}
