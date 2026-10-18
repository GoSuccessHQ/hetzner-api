<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

/**
 * Whether a server is protected against deletion and rebuilding.
 */
final readonly class ServerProtection
{
    public function __construct(
        public bool $delete,
        public bool $rebuild,
    ) {}
}
