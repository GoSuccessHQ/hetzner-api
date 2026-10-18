<?php

declare(strict_types=1);

/**
 * Shared setup of the examples: the autoloader and the API token from the
 * environment (HETZNER_CLOUD_TOKEN), e.g. `set -a; . ./.env; set +a`.
 */

require __DIR__ . '/../vendor/autoload.php';

$token = getenv('HETZNER_CLOUD_TOKEN');

if (!is_string($token) || $token === '') {
    fwrite(STDERR, "Set HETZNER_CLOUD_TOKEN.\n");

    exit(1);
}

return $token;
