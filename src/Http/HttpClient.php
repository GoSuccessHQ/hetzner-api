<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Http;

use GoSuccess\Hetzner\Exception\TransportException;
use SensitiveParameter;

/**
 * Minimal HTTP transport abstraction.
 *
 * The library ships with {@see CurlHttpClient} (no Composer dependencies). Provide
 * your own implementation to route requests through an existing HTTP stack, e.g.
 * Guzzle, without the library depending on it.
 */
interface HttpClient
{
    /**
     * Send a request and return the response.
     *
     * Implementations MUST:
     * - throw a {@see TransportException} for transport-level failures (connection
     *   errors, timeouts, …), but never for HTTP error status codes, which are
     *   returned as a normal {@see Response};
     * - return the response headers with lower-cased names;
     * - not follow redirects, so a request is never sent to another host.
     *
     * The request carries the API token and may carry user data with secrets. Mark
     * the parameter `#[SensitiveParameter]` in an implementation as well (PHP does not
     * inherit the attribute), so that a stack trace with arguments never holds it.
     *
     * @throws TransportException
     */
    public function send(#[SensitiveParameter] Request $request): Response;
}
