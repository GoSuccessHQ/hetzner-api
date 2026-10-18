<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

/**
 * The protocols a firewall rule can match.
 */
enum FirewallProtocol: string
{
    case Tcp = 'tcp';
    case Udp = 'udp';
    case Icmp = 'icmp';
    case Esp = 'esp';
    case Gre = 'gre';

    /**
     * Whether rules of this protocol take a port.
     */
    public function hasPorts(): bool
    {
        return $this === self::Tcp || $this === self::Udp;
    }
}
