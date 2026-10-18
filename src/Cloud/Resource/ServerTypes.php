<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Resource;

use GoSuccess\Hetzner\Cloud\Model\ServerType;

/**
 * The server types (`GET /server_types`); filter `name`.
 *
 * @extends Listing<ServerType>
 */
final class ServerTypes extends Listing
{
    protected const string PATH = '/server_types';

    protected const string ITEMS = 'server_types';

    protected const string ITEM = 'server_type';

    protected function map(array $data, string $path): ServerType
    {
        return ServerType::fromJson($data, $path);
    }
}
