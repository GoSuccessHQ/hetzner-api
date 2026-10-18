<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Exception;

/**
 * The request payload failed validation (HTTP 422).
 */
final class ValidationException extends ApiException {}
