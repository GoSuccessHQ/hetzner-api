<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

/**
 * A new firewall and the actions that apply its rules and targets.
 */
final readonly class CreatedFirewall
{
    /**
     * @param list<Action> $actions
     */
    public function __construct(
        public Firewall $firewall,
        public array $actions,
    ) {}
}
