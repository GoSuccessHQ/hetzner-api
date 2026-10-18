<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

/**
 * Why an action failed, as Hetzner reports it.
 */
final readonly class ActionError
{
    public function __construct(
        public string $code,
        public string $message,
    ) {}
}
