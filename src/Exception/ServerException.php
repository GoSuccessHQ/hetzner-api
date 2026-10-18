<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Exception;

/**
 * The service failed to process the request (HTTP 5xx).
 */
final class ServerException extends ApiException {}
