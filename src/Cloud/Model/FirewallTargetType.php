<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

/**
 * What a firewall is applied to.
 */
enum FirewallTargetType: string
{
    case Server = 'server';
    case LabelSelector = 'label_selector';
}
