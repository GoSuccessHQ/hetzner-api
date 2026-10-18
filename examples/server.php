<?php

declare(strict_types=1);

/**
 * Orders a server, waits until it exists, and deletes it again. This costs
 * money: run it only in a test project.
 *
 * Usage: php examples/server.php <server-type> <location>
 */

use GoSuccess\Hetzner\Cloud\HetznerCloud;
use GoSuccess\Hetzner\Cloud\Model\ActionStatus;
use GoSuccess\Hetzner\Cloud\Model\CreateServer;
use GoSuccess\Hetzner\Exception\ApiException;

$token = require __DIR__ . '/bootstrap.php';
assert(is_string($token));

$cloud = new HetznerCloud($token);

try {
    $created = $cloud->servers()->create(new CreateServer(
        name: 'example-' . bin2hex(random_bytes(3)),
        serverType: $argv[1] ?? 'cx23',
        image: 'ubuntu-24.04',
        location: $argv[2] ?? 'nbg1',
        labels: ['example' => '1'],
        enableIpv4: false,
        startAfterCreate: false,
    ));
} catch (ApiException $exception) {
    // E.g. resource_unavailable when the type cannot be ordered there.
    fwrite(STDERR, "Refused: {$exception->errorCode}\n");

    exit(1);
}

$action = $cloud->actions()->wait($created->action, timeout: 300.0);
printf("Server %d: %s\n", $created->server->id, $action->status === ActionStatus::Success ? 'created' : "failed ({$action->error?->code})");

$cloud->servers()->delete($created->server->id);
echo "Deleted.\n";
