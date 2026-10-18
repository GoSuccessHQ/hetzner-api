<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Exception;

/**
 * Normalizes the error envelope of the Cloud API, `{"error": {"code": "…",
 * "message": "…", "details": …}}` (specification, 2026-10-18): the code is the
 * error code, the message the message. Plain text or HTML, e.g. from a proxy,
 * is shortened.
 *
 * @internal
 */
final readonly class ErrorDetails
{
    private const int MAX_MESSAGE_BYTES = 500;

    private function __construct(
        public ?string $message,
        public ?string $errorCode,
    ) {}

    public static function parse(string $body): self
    {
        if (trim($body) === '') {
            return new self(null, null);
        }

        $decoded = json_decode($body, true);

        if (!\is_array($decoded)) {
            // Plain text or an HTML error page.
            return new self(self::excerpt($body), null);
        }

        $error = $decoded['error'] ?? null;

        if (!\is_array($error)) {
            return new self(null, null);
        }

        $message = self::text($error['message'] ?? null);

        return new self($message === null ? null : self::excerpt($message), self::text($error['code'] ?? null));
    }

    private static function text(mixed $value): ?string
    {
        return \is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function excerpt(string $text): string
    {
        $text = trim($text);

        if (\strlen($text) <= self::MAX_MESSAGE_BYTES) {
            return $text;
        }

        $cut = substr($text, 0, self::MAX_MESSAGE_BYTES);

        // Drop a trailing, incomplete multi-byte sequence (ext-mbstring is not required).
        return (preg_replace('/[\xC0-\xFF][\x80-\xBF]*$/', '', $cut) ?? $cut) . '…';
    }
}
