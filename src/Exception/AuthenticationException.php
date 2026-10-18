<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Exception;

/**
 * The API refused the token (HTTP 401, `unauthorized`): missing, invalid or
 * revoked.
 */
final class AuthenticationException extends ApiException {}
