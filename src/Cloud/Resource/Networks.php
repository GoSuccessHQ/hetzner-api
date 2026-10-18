<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Resource;

use GoSuccess\Hetzner\Cloud\Model\Network;

/**
 * The private networks (`GET /networks`); filters `name`, `label_selector`, `sort`.
 *
 * @extends Listing<Network>
 */
final class Networks extends Listing
{
    protected const string PATH = '/networks';

    protected const string ITEMS = 'networks';

    protected const string ITEM = 'network';

    protected function map(array $data, string $path): Network
    {
        return Network::fromJson($data, $path);
    }
}
