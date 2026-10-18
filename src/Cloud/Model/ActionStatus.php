<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

/**
 * The state of an action.
 */
enum ActionStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Error = 'error';
}
