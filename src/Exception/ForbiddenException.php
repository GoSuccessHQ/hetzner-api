<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Exception;

/**
 * The token may not do this (HTTP 403, `forbidden`), e.g. a read-only token for a
 * write.
 */
final class ForbiddenException extends ApiException {}
