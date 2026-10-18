<?php

declare(strict_types=1);

/**
 * Lists the server types that can be ordered now, with their monthly price per
 * location. Read-only.
 *
 * Usage: php examples/catalog.php
 */

use GoSuccess\Hetzner\Cloud\HetznerCloud;

$token = require __DIR__ . '/bootstrap.php';
assert(is_string($token));

$cloud = new HetznerCloud($token);

foreach ($cloud->serverTypes()->all() as $type) {
    foreach ($type->locations as $location) {
        if (!$location->available || $location->deprecation !== null) {
            continue;
        }

        foreach ($type->prices as $price) {
            if ($price->location === $location->name) {
                printf("%-8s %-5s %2d cores %5.1f GB %4d GB  %s EUR/month\n", $type->name, $location->name, $type->cores, $type->memory, $type->disk, $price->monthlyGross);
            }
        }
    }
}

printf("Requests left this hour: %d\n", $cloud->rateLimit()->remaining ?? 0);
