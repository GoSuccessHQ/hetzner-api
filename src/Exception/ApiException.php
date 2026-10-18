<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Exception;

use GoSuccess\Hetzner\Http\Request;
use GoSuccess\Hetzner\Http\Response;
use RuntimeException;
use SensitiveParameter;

/**
 * Base class for all errors the API reports as an HTTP error status (also a
 * redirect, which the client never follows).
 *
 * The API's error message and code are normalized into the exception message
 * and {@see $errorCode}, while the raw body stays available in
 * {@see $responseBody}.
 */
class ApiException extends RuntimeException implements HetznerException
{
    /**
     * @param int         $statusCode   HTTP status code of the response.
     * @param string      $responseBody Raw response body.
     * @param string|null $errorCode    Hetzner's `error.code`, e.g. `not_found`,
     *                                  `invalid_input` or `locked`; null when the body
     *                                  has no error envelope (e.g. a proxy's page).
     * @param array<string, list<string>> $fields The messages per field of an
     *                                  `invalid_input` error (`details.fields`).
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $responseBody = '',
        public readonly ?string $errorCode = null,
        public readonly array $fields = [],
    ) {
        parent::__construct($message, $statusCode);
    }

    /**
     * Build the most specific exception type for an error response.
     *
     * @param Request    $request The request that failed. It carries the token and
     *                            may carry user data, so it is marked sensitive: the
     *                            exception is created in this frame, and its trace
     *                            must not hold it.
     * @param float|null $now     Current Unix time, to turn a `Retry-After` date into
     *                            {@see RateLimitException::$retryAfter}; defaults to the
     *                            system time.
     */
    public static function fromResponse(Response $response, #[SensitiveParameter] Request $request, ?float $now = null): self
    {
        $status = $response->statusCode;
        $body = $response->body;
        $details = ErrorDetails::parse($body);

        // The query string may carry filters, which have no place in logs.
        $target = explode('?', $request->uri, 2)[0];
        $message = "{$request->method->value} {$target} failed with HTTP {$status}";

        if ($response->reasonPhrase !== '') {
            $message .= " {$response->reasonPhrase}";
        }

        if ($details->message !== null) {
            $message .= ": {$details->message}";
        }

        if ($details->errorCode !== null) {
            $message .= " ({$details->errorCode})";
        }

        $arguments = [$message, $status, $body, $details->errorCode, $details->fields];

        return match (true) {
            $status === 400 => new BadRequestException(...$arguments),
            $status === 401 => new AuthenticationException(...$arguments),
            $status === 403 => new ForbiddenException(...$arguments),
            $status === 404 => new NotFoundException(...$arguments),
            $status === 409 => new ConflictException(...$arguments),
            $status === 422 => new ValidationException(...$arguments),
            $status === 429 => new RateLimitException(
                ...$arguments,
                retryAfter: RateLimitException::retryAfter($response, $now ?? microtime(true)),
            ),
            $status >= 500 => new ServerException(...$arguments),
            default => new self(...$arguments),
        };
    }

    /**
     * The decoded JSON error body, or null if the body is not JSON.
     */
    public function decodedBody(): mixed
    {
        return $this->responseBody === '' ? null : json_decode($this->responseBody, true);
    }
}
