<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Tests\Support;

use LogicException;
use Throwable;

/**
 * Inspects the arguments that exception traces record, to check that secrets
 * stay out of them.
 */
final class ExceptionTrace
{
    /**
     * Run an action with arguments recorded in exception traces, as PHP does by
     * default without a php.ini (php.ini-production turns it off), and return
     * the exception it throws.
     *
     * @param callable(): mixed $action
     */
    public static function capture(callable $action): Throwable
    {
        $previous = ini_set('zend.exception_ignore_args', '0');

        try {
            $action();
        } catch (Throwable $e) {
            return $e;
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }

        throw new LogicException('The action did not throw.');
    }

    /**
     * The arguments of every frame as JSON, like a logger that serializes the
     * trace would write them.
     */
    public static function arguments(Throwable $e): string
    {
        return self::encode($e->getTrace());
    }

    /**
     * The arguments of the package's own frames only (not of the tests or
     * their doubles), as JSON.
     */
    public static function packageArguments(Throwable $e): string
    {
        $own = array_filter(
            $e->getTrace(),
            static fn(array $frame): bool => str_starts_with($frame['class'] ?? '', 'GoSuccess\\Hetzner\\') && !str_starts_with($frame['class'] ?? '', 'GoSuccess\\Hetzner\\Tests\\'),
        );

        return self::encode(array_values($own));
    }

    /**
     * @param list<array<string, mixed>> $trace
     */
    private static function encode(array $trace): string
    {
        return (string) json_encode(array_map(static fn(array $frame): mixed => $frame['args'] ?? null, $trace), \JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
