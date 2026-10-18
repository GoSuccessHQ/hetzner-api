<?php

declare(strict_types=1);

/**
 * Refreshes the snapshot of the Cloud API specification in resources/specs.
 * The weekly spec-drift workflow runs it and fails when the snapshot changed,
 * so a change at Hetzner to an operation or field the client uses is noticed
 * (SpecTest checks the snapshot).
 */

use GoSuccess\Hetzner\Http\CurlHttpClient;
use GoSuccess\Hetzner\Http\Method;
use GoSuccess\Hetzner\Http\Request;

require __DIR__ . '/../vendor/autoload.php';

$sources = [
    'cloud.spec.json' => 'https://docs.hetzner.cloud/cloud.spec.json',
];

$http = new CurlHttpClient();

foreach ($sources as $file => $url) {
    $response = $http->send(new Request(Method::Get, $url, ['Accept' => 'application/json']));

    if (!$response->isSuccessful) {
        fwrite(STDERR, "{$url}: HTTP {$response->statusCode}\n");

        exit(1);
    }

    // Pretty and stable, so a diff shows what changed.
    $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
    $body = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";

    file_put_contents(__DIR__ . "/../resources/specs/{$file}", $body);
    echo "{$file}\n";
}
