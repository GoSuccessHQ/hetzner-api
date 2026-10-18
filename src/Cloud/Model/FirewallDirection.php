<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

/**
 * Whether a rule allows incoming or outgoing traffic.
 */
enum FirewallDirection: string
{
    case In = 'in';
    case Out = 'out';
}
