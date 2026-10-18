<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Exception;

use GoSuccess\Hetzner\Cloud\Model\Action;
use GoSuccess\Hetzner\Exception\HetznerException;
use RuntimeException;

/**
 * An action was still running when the caller's limit for waiting ran out; it
 * may still finish. {@see $action} is its last known state.
 */
final class ActionTimeoutException extends RuntimeException implements HetznerException
{
    public function __construct(public readonly Action $action, float $timeout)
    {
        parent::__construct("Action {$action->id} ({$action->command}) was still running after {$timeout} s.");
    }
}
