<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Resource;

use GoSuccess\Hetzner\Cloud\Model\SshKey;

/**
 * The SSH keys (`GET /ssh_keys`); filters `name`, `fingerprint`, `label_selector`, `sort`.
 *
 * @extends Listing<SshKey>
 */
final class SshKeys extends Listing
{
    protected const string PATH = '/ssh_keys';

    protected const string ITEMS = 'ssh_keys';

    protected const string ITEM = 'ssh_key';

    protected function map(array $data, string $path): SshKey
    {
        return SshKey::fromJson($data, $path);
    }
}
