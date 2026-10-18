<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Http;

/**
 * HTTP methods of the Cloud API.
 */
enum Method: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
    case Patch = 'PATCH';
    case Delete = 'DELETE';

    /**
     * Whether repeating the request has the same effect as sending it once;
     * only such requests are retried after a server error or a lost connection.
     */
    public function isIdempotent(): bool
    {
        return match ($this) {
            self::Get, self::Put, self::Delete => true,
            self::Post, self::Patch => false,
        };
    }
}
