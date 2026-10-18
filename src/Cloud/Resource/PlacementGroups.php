<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Resource;

use GoSuccess\Hetzner\Cloud\Model\PlacementGroup;

/**
 * The placement groups (`GET /placement_groups`); filters `name`, `label_selector`, `type`, `sort`.
 *
 * @extends Listing<PlacementGroup>
 */
final class PlacementGroups extends Listing
{
    protected const string PATH = '/placement_groups';

    protected const string ITEMS = 'placement_groups';

    protected const string ITEM = 'placement_group';

    protected function map(array $data, string $path): PlacementGroup
    {
        return PlacementGroup::fromJson($data, $path);
    }
}
